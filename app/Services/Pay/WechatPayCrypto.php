<?php

declare(strict_types=1);

namespace App\Services\Pay;

use App\Exceptions\WePayException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use OpenSSLAsymmetricKey;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * 微信支付签名与证书辅助类 (API v3)
 *
 * 负责签名生成、回调验签、资源解密、平台证书下载/缓存/回退等底层逻辑，
 * 与商户业务解耦。
 */
final class WechatPayCrypto
{
    /**
     * @param  array<array-key, mixed>  $config
     */
    public function __construct(
        protected array $config,
        protected OpenSSLAsymmetricKey $privateKey,
        protected string $type = 'native',
    ) {}

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    public function signPayData(array $payload): array
    {
        $message = match ($this->type) {
            'js' => "{$payload['appId']}\n{$payload['timeStamp']}\n{$payload['nonceStr']}\n{$payload['package']}\n",
            'app' => "{$payload['appid']}\n{$payload['timestamp']}\n{$payload['noncestr']}\n{$payload['prepayid']}\n",
            default => '',
        };

        if ($message !== '') {
            $key = $this->type === 'js' ? 'paySign' : 'sign';
            $payload[$key] = Rsa::sign($message, $this->privateKey);
        }

        return $payload;
    }

    /**
     * @param  array<array-key, mixed>  $headers
     *
     * @throws WePayException
     */
    public function verifySignature(array $headers, string $body): bool
    {
        $signature = (string) (head(Arr::wrap(data_get($headers, 'wechatpay-signature'))) ?? '');
        $timestamp = (string) (head(Arr::wrap(data_get($headers, 'wechatpay-timestamp'))) ?? '');
        $nonce = (string) (head(Arr::wrap(data_get($headers, 'wechatpay-nonce'))) ?? '');
        $serial = (string) (head(Arr::wrap(data_get($headers, 'wechatpay-serial'))) ?? '');

        if ($signature === '' || $timestamp === '' || $nonce === '' || $serial === '') {
            return false;
        }

        $certs = $this->getPlatformCerts($this->resolveSerial());
        $cert = data_get($certs, $serial);
        if (! is_string($cert) || $cert === '') {
            return false;
        }

        $message = "{$timestamp}\n{$nonce}\n{$body}\n";

        return Rsa::verify($message, $signature, Rsa::from($cert, Rsa::KEY_TYPE_PUBLIC));
    }

    /**
     * @param  array<array-key, mixed>  $resource
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    public function decryptResource(array $resource): array
    {
        $ciphertext = (string) data_get($resource, 'ciphertext', '');
        $key = (string) data_get($this->config, 'key', '');
        $nonce = (string) data_get($resource, 'nonce', '');
        $associatedData = (string) data_get($resource, 'associated_data', '');

        $decrypted = AesGcm::decrypt($ciphertext, $key, $nonce, $associatedData);
        $decoded = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    public function getPlatformCerts(string $serial): array
    {
        $mchId = (string) data_get($this->config, 'mch_id', '');
        $key = (string) data_get($this->config, 'key', '');
        $cacheKey = "wechat_platform_certs_{$mchId}";

        return cache()->remember($cacheKey, now()->addHours(24), function () use ($serial, $mchId, $key): array {
            $timestamp = now()->timestamp;
            $nonce = Str::random(32);
            $sign = Rsa::sign("GET\n/v3/certificates\n{$timestamp}\n{$nonce}\n\n", $this->privateKey);

            $response = Http::withHeaders([
                'Authorization' => sprintf(
                    'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
                    $mchId,
                    $nonce,
                    $timestamp,
                    $serial,
                    $sign,
                ),
                'Accept' => 'application/json',
                'User-Agent' => 'WeChatPay-SDK/Merged',
            ])->get('https://api.mch.weixin.qq.com/v3/certificates');

            if (! $response->successful()) {
                // 回退本地磁盘搜索
                return $this->loadLocalCerts();
            }

            $responseData = $response->json();
            $data = is_array($responseData) ? data_get($responseData, 'data') : null;
            $items = is_array($data) ? $data : [];

            $certs = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $serialNo = (string) data_get($item, 'serial_no', '');
                $enc = data_get($item, 'encrypt_certificate');
                if (! is_array($enc)) {
                    continue;
                }

                $ciphertext = (string) data_get($enc, 'ciphertext', '');
                $encNonce = (string) data_get($enc, 'nonce', '');
                $encAssoc = (string) data_get($enc, 'associated_data', '');

                if ($serialNo !== '' && $ciphertext !== '') {
                    $certs[$serialNo] = AesGcm::decrypt($ciphertext, $key, $encNonce, $encAssoc);
                }
            }

            return $certs !== [] ? $certs : $this->loadLocalCerts();
        });
    }

    /**
     * @return array<string, string>
     */
    public function loadLocalCerts(): array
    {
        $certs = [];
        $files = Arr::wrap(glob(storage_path('certs/wechat/platform_*.pem')));
        foreach ($files as $file) {
            if (! is_string($file) || ! file_exists($file)) {
                continue;
            }

            $raw = rescue(static fn (): string|false => file_get_contents($file), false, report: false);
            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $parsed = rescue(static fn (): array|false => openssl_x509_parse($raw), false, report: false);
            $serialNumber = data_get($parsed, 'serialNumber');
            if (is_string($serialNumber) && $serialNumber !== '') {
                $certs[Str::upper($serialNumber)] = $raw;
            }
        }

        return $certs;
    }

    public function resolveSerial(): string
    {
        $certSerial = data_get($this->config, 'certificate_serial');
        if (is_string($certSerial) && $certSerial !== '') {
            return $certSerial;
        }

        $certPath = data_get($this->config, 'cert_path');
        if (is_string($certPath) && $certPath !== '' && file_exists($certPath)) {
            $raw = rescue(static fn (): string|false => file_get_contents($certPath), false, report: false);
            if (is_string($raw) && $raw !== '') {
                $parsed = rescue(static fn (): array|false => openssl_x509_parse($raw), false, report: false);
                $serialNumber = data_get($parsed, 'serialNumber');
                if (is_string($serialNumber) && $serialNumber !== '') {
                    return Str::upper($serialNumber);
                }
            }
        }

        throw WePayException::configError('缺少 certificate_serial 或 cert_path');
    }
}

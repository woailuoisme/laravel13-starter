<?php

declare(strict_types=1);

namespace App\Services\Pay;

use App\Exceptions\WePayException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;
use Throwable;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * 微信支付服务类 (API v3)
 *
 * 合并重构版：
 * - 统一入口，支持 App, Native, JSAPI, H5 支付方式
 * - 自动处理平台证书下载与缓存
 * - 完整的支付、查询、退款、回调验证逻辑
 * - 使用 PHP 8.3+ 特性 (Constructor Promotion, Match, Typed Properties)
 */
class WechatPayService
{
    /** 支持的支付类型 */
    private const array PAYMENT_TYPES = [
        'app' => 'v3/pay/transactions/app',
        'native' => 'v3/pay/transactions/native',
        'js' => 'v3/pay/transactions/jsapi',
        'h5' => 'v3/pay/transactions/h5',
    ];

    /** @var BuilderChainable SDK 客户端 */
    protected BuilderChainable $client;

    /** @var OpenSSLAsymmetricKey 缓存的私钥实例 */
    protected OpenSSLAsymmetricKey $privateKey;

    /**
     * @param string $type 默认支付类型 (native / js / app / h5)
     * @param array $config 覆盖配置 (默认读取 config('pay.wechat'))
     */
    public function __construct(
        protected string $type = 'native',
        protected array $config = [],
    ) {
        $this->config = empty($this->config) ? config('pay.wechat', []) : $this->config;
        $this->ensureConfigIsValid();
        $this->initializeClient();
    }

    // --- 快速初始化方法 (工厂模式) ---

    public static function app(array $config = []): self
    {
        return new self('app', $config);
    }

    public static function native(array $config = []): self
    {
        return new self('native', $config);
    }

    public static function js(array $config = []): self
    {
        return new self('js', $config);
    }

    public static function h5(array $config = []): self
    {
        return new self('h5', $config);
    }

    // --- 核心业务接口 ---

    /**
     * 统一支付下单
     *
     * @param string $outTradeNo 商户系统内部订单号
     * @param int $total 分单位订单金额
     * @param string $description 商品描述
     * @param array $extra 扩展参数 (openid / client_ip 等)
     */
    public function pay(string $outTradeNo, int $total, string $description, array $extra = []): array
    {
        $params = array_merge([
            'out_trade_no' => $outTradeNo,
            'amount' => $total,
            'description' => $description,
        ], $extra);

        $this->validateOrderParams($params);

        try {
            Log::info("微信支付下单: [{$this->type}] {$outTradeNo}", ['total' => $total]);

            $responseData = $this->callApi(self::PAYMENT_TYPES[$this->type], 'POST', $this->buildOrderBody($params));

            return $this->formatPaymentResult($responseData);
        } catch (Throwable $e) {
            Log::error("微信支付下单失败: {$outTradeNo}", ['error' => $e->getMessage()]);
            throw ($e instanceof WePayException) ? $e : WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 查询订单状态
     */
    public function query(string $outTradeNo): array
    {
        try {
            $result = $this->callApi("v3/pay/transactions/out-trade-no/{$outTradeNo}", 'GET', [], [
                'mchid' => $this->config['mch_id'],
            ]);

            return [
                'success' => true,
                'is_paid' => ($result['trade_state'] ?? '') === 'SUCCESS',
                'raw' => $result,
            ];
        } catch (Throwable $e) {
            Log::error("查询订单失败: {$outTradeNo}", ['error' => $e->getMessage()]);
            throw WePayException::paymentFailed("查询失败: {$e->getMessage()}");
        }
    }

    /**
     * 申请退款
     */
    public function refund(string $outTradeNo, string $outRefundNo, int $total, int $refund, string $reason = '商户退款'): array
    {
        try {
            $body = [
                'out_trade_no' => $outTradeNo,
                'out_refund_no' => $outRefundNo,
                'reason' => $reason,
                'amount' => [
                    'refund' => $refund,
                    'total' => $total,
                    'currency' => 'CNY',
                ],
            ];

            if (! empty($this->config['refund_notify_url'])) {
                $body['notify_url'] = $this->config['refund_notify_url'];
            }

            $result = $this->callApi('v3/refund/domestic/refunds', 'POST', $body);

            return ['success' => true, 'id' => $result['refund_id'] ?? '', 'status' => $result['status'] ?? ''];
        } catch (Throwable $e) {
            Log::error("微信退款失败: {$outTradeNo}", ['error' => $e->getMessage()]);
            throw WePayException::paymentFailed("退款失败: {$e->getMessage()}");
        }
    }

    /**
     * 关闭订单
     */
    public function close(string $outTradeNo): bool
    {
        try {
            $this->callApi("v3/pay/transactions/out-trade-no/{$outTradeNo}/close", 'POST', [
                'mchid' => $this->config['mch_id'],
            ]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 处理并验证回调通知
     */
    public function verifyCallback(?array $headers = null, ?string $body = null): array
    {
        $headers ??= request()->headers->all();
        $body ??= request()->getContent();

        try {
            if (! $this->verifySignature($headers, (string) $body)) {
                throw WePayException::signatureError();
            }

            $data = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
            $decrypted = $this->decryptResource($data['resource'] ?? []);

            Log::info('微信支付回调处理成功', ['event' => $data['event_type'] ?? '']);

            return [
                'success' => true,
                'event_type' => $data['event_type'] ?? '',
                'resource' => $decrypted,
            ];
        } catch (Throwable $e) {
            Log::error('微信回调验证失败', ['error' => $e->getMessage()]);
            throw WePayException::signatureError($e->getMessage());
        }
    }

    // --- 内部逻辑分发 ---

    protected function initializeClient(): void
    {
        try {
            // 加载 Rsa 私钥
            $this->privateKey = Rsa::from('file://'.$this->config['private_key_path'], Rsa::KEY_TYPE_PRIVATE);

            // 解析证书序列号
            $serial = $this->resolveSerial();

            // 获取平台证书 (带缓存)
            $platformCerts = $this->getPlatformCerts($serial);

            $this->client = Builder::factory([
                'mchid' => $this->config['mch_id'],
                'serial' => $serial,
                'privateKey' => $this->privateKey,
                'certs' => $platformCerts,
            ]);
        } catch (Throwable $e) {
            throw WePayException::configError("SDK 初始化失败: {$e->getMessage()}");
        }
    }

    protected function callApi(string $endpoint, string $method, array $json = [], array $query = []): array
    {
        try {
            $options = [];
            if (! empty($json)) {
                $options['json'] = $json;
            }
            if (! empty($query)) {
                $options['query'] = $query;
            }

            $response = $this->client->chain($endpoint)->{$method}($options);

            $body = $response->getBody()->getContents();

            if ($response->getStatusCode() >= 400) {
                throw WePayException::fromWechatResponse($body, $response->getStatusCode());
            }

            return json_decode($body, true) ?: [];
        } catch (ClientException|ServerException $e) {
            $body = $e->getResponse()->getBody()->getContents();
            throw WePayException::fromWechatResponse($body, $e->getResponse()->getStatusCode());
        } catch (RequestException $e) {
            throw WePayException::networkError($e->getMessage());
        }
    }

    protected function buildOrderBody(array $params): array
    {
        $body = [
            'appid' => $this->config['app_id'],
            'mchid' => $this->config['mch_id'],
            'description' => $params['description'],
            'out_trade_no' => $params['out_trade_no'],
            'notify_url' => $this->config['notify_url'] ?? '',
            'amount' => [
                'total' => (int) $params['amount'],
                'currency' => 'CNY',
            ],
        ];

        return match ($this->type) {
            'js' => array_merge($body, ['payer' => ['openid' => $params['openid']]]),
            'h5' => array_merge($body, [
                'scene_info' => [
                    'payer_client_ip' => $params['client_ip'] ?? request()->ip(),
                    'h5_info' => ['type' => 'Wap'],
                ],
            ]),
            default => $body,
        };
    }

    protected function formatPaymentResult(array $data): array
    {
        $base = ['success' => true, 'type' => $this->type];

        return match ($this->type) {
            'js' => array_merge($base, ['js_config' => $this->signPayData([
                'appId' => $this->config['app_id'],
                'timeStamp' => (string) time(),
                'nonceStr' => Str::random(32),
                'package' => 'prepay_id='.$data['prepay_id'],
                'signType' => 'RSA',
            ])]),
            'app' => array_merge($base, ['app_config' => $this->signPayData([
                'appid' => $this->config['app_id'],
                'partnerid' => $this->config['mch_id'],
                'prepayid' => $data['prepay_id'],
                'package' => 'Sign=WXPay',
                'noncestr' => Str::random(32),
                'timestamp' => (string) time(),
            ])]),
            'native' => array_merge($base, [
                'code_url' => $data['code_url'] ?? '',
                'qr_code' => isset($data['code_url']) ? "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=".urlencode($data['code_url']) : '',
            ]),
            'h5' => array_merge($base, ['h5_url' => $data['h5_url'] ?? '']),
            default => $base,
        };
    }

    protected function signPayData(array $payload): array
    {
        $message = match ($this->type) {
            'js' => "{$payload['appId']}\n{$payload['timeStamp']}\n{$payload['nonceStr']}\n{$payload['package']}\n",
            'app' => "{$payload['appid']}\n{$payload['timestamp']}\n{$payload['noncestr']}\n{$payload['prepayid']}\n",
            default => '',
        };

        if ($message) {
            $key = ($this->type === 'js') ? 'paySign' : 'sign';
            $payload[$key] = Rsa::sign($message, $this->privateKey);
        }

        return $payload;
    }

    // --- 签名与证书辅助 ---

    protected function verifySignature(array $headers, string $body): bool
    {
        $signature = $headers['wechatpay-signature'][0] ?? '';
        $timestamp = $headers['wechatpay-timestamp'][0] ?? '';
        $nonce = $headers['wechatpay-nonce'][0] ?? '';
        $serial = $headers['wechatpay-serial'][0] ?? '';

        if (! $signature || ! $timestamp || ! $nonce || ! $serial) {
            return false;
        }

        $certs = $this->getPlatformCerts($this->resolveSerial());
        if (empty($certs[$serial])) {
            return false;
        }

        $message = "{$timestamp}\n{$nonce}\n{$body}\n";

        return Rsa::verify($message, $signature, Rsa::from($certs[$serial], Rsa::KEY_TYPE_PUBLIC));
    }

    protected function decryptResource(array $resource): array
    {
        $decrypted = AesGcm::decrypt(
            $resource['ciphertext'] ?? '',
            $this->config['key'] ?? '',
            $resource['nonce'] ?? '',
            $resource['associated_data'] ?? '',
        );

        return json_decode($decrypted, true) ?: [];
    }

    protected function getPlatformCerts(string $serial): array
    {
        $cacheKey = "wechat_platform_certs_{$this->config['mch_id']}";

        return cache()->remember($cacheKey, now()->addHours(24), function () use ($serial) {
            $timestamp = time();
            $nonce = Str::random(32);
            $sign = Rsa::sign("GET\n/v3/certificates\n{$timestamp}\n{$nonce}\n\n", $this->privateKey);

            $response = Http::withHeaders([
                'Authorization' => sprintf('WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"', $this->config['mch_id'], $nonce, $timestamp, $serial, $sign),
                'Accept' => 'application/json',
                'User-Agent' => 'WeChatPay-SDK/Merged',
            ])->get('https://api.mch.weixin.qq.com/v3/certificates');

            if (! $response->successful()) {
                // 回退本地磁盘搜索
                return $this->loadLocalCerts();
            }

            $certs = [];
            foreach ($response->json()['data'] ?? [] as $item) {
                $certs[$item['serial_no']] = AesGcm::decrypt($item['encrypt_certificate']['ciphertext'], $this->config['key'], $item['encrypt_certificate']['nonce'], $item['encrypt_certificate']['associated_data']);
            }

            return $certs ?: $this->loadLocalCerts();
        }) ?: [];
    }

    protected function loadLocalCerts(): array
    {
        $certs = [];
        foreach (glob(storage_path('certs/wechat/platform_*.pem')) ?: [] as $file) {
            if ($raw = file_get_contents($file)) {
                $parsed = openssl_x509_parse($raw);
                if (isset($parsed['serialNumber'])) {
                    $certs[mb_strtoupper($parsed['serialNumber'])] = $raw;
                }
            }
        }

        return $certs;
    }

    protected function resolveSerial(): string
    {
        if (! empty($this->config['certificate_serial'])) {
            return $this->config['certificate_serial'];
        }

        if (! empty($this->config['cert_path']) && $raw = @file_get_contents($this->config['cert_path'])) {
            $parsed = openssl_x509_parse($raw);

            return mb_strtoupper($parsed['serialNumber'] ?? '');
        }

        throw WePayException::configError('缺少 certificate_serial 或 cert_path');
    }

    protected function ensureConfigIsValid(): void
    {
        $keys = ['app_id', 'mch_id', 'key', 'private_key_path'];
        foreach ($keys as $k) {
            if (empty($this->config[$k])) {
                throw WePayException::configError("微信支付配置缺失: {$k}");
            }
        }

        if (! str_starts_with($this->config['private_key_path'], '/')) {
            $this->config['private_key_path'] = base_path($this->config['private_key_path']);
        }
    }

    protected function validateOrderParams(array $params): void
    {
        if (empty($params['out_trade_no']) || empty($params['amount'])) {
            throw WePayException::configError('下单参数不完整');
        }
        if ($this->type === 'js' && empty($params['openid'])) {
            throw WePayException::configError('JSAPI 支付缺少 openid');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pay;

use App\Exceptions\WePayException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use OpenSSLAsymmetricKey;
use Throwable;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;

/**
 * 微信支付服务类 (API v3)
 *
 * 合并重构版：
 * - 统一入口，支持 App, Native, JSAPI, H5 支付方式
 * - 完整的支付、查询、退款、回调验证逻辑
 * - 签名、证书、解密等底层逻辑委托给 WechatPayCrypto
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

    /** @var WechatPayCrypto 签名与证书辅助 */
    protected WechatPayCrypto $crypto;

    /**
     * @param  string  $type  默认支付类型 (native / js / app / h5)
     * @param  array  $config  覆盖配置 (默认读取 config('pay.wechat'))
     *
     * @throws WePayException
     */
    public function __construct(
        protected string $type = 'native',
        protected array $config = [],
    ) {
        $this->config = $this->config === [] ? config('pay.wechat', []) : $this->config;
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
     * @param  string  $outTradeNo  商户系统内部订单号
     * @param  int  $total  分单位订单金额
     * @param  string  $description  商品描述
     * @param  array  $extra  扩展参数 (openid / client_ip 等)
     */
    public function pay(string $outTradeNo, int $total, string $description, array $extra = []): array
    {
        $params = ['out_trade_no' => $outTradeNo, 'amount' => $total, 'description' => $description, ...$extra];

        $this->validateOrderParams($params);

        try {
            Log::info("微信支付下单: [{$this->type}] {$outTradeNo}", ['total' => $total]);

            $responseData = $this->callApi(self::PAYMENT_TYPES[$this->type], 'POST', $this->buildOrderBody($params));

            return $this->formatPaymentResult($responseData);
        } catch (Throwable $e) {
            Log::error("微信支付下单失败: {$outTradeNo}", ['error' => $e->getMessage()]);

            throw $e instanceof WePayException ? $e : WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 查询订单状态
     *
     * @throws WePayException
     */
    public function query(string $outTradeNo): array
    {
        try {
            $result = $this->callApi(
                "v3/pay/transactions/out-trade-no/{$outTradeNo}",
                'GET',
                [],
                [
                    'mchid' => $this->config['mch_id'],
                ],
            );

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
    public function refund(
        string $outTradeNo,
        string $outRefundNo,
        int $total,
        int $refund,
        string $reason = '商户退款',
    ): array {
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

            if (is_string($this->config['refund_notify_url'] ?? null) && $this->config['refund_notify_url'] !== '') {
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
            throw_unless($this->crypto->verifySignature($headers, (string) $body), WePayException::signatureError());

            $data = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
            $rawResource = is_array($data) ? data_get($data, 'resource') : null;
            $resource = is_array($rawResource) ? $rawResource : [];
            $decrypted = $this->crypto->decryptResource($resource);
            $eventType = is_array($data) && is_string(data_get($data, 'event_type'))
                ? (string) data_get($data, 'event_type')
                : '';

            Log::info('微信支付回调处理成功', ['event' => $eventType]);

            return [
                'success' => true,
                'event_type' => $eventType,
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

            $this->crypto = new WechatPayCrypto($this->config, $this->privateKey, $this->type);

            // 解析证书序列号
            $serial = $this->crypto->resolveSerial();

            // 获取平台证书 (带缓存)
            $platformCerts = $this->crypto->getPlatformCerts($serial);

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

    /**
     * @throws WePayException|JsonException
     */
    protected function callApi(string $endpoint, string $method, array $json = [], array $query = []): array
    {
        try {
            $options = [];
            if ($json !== []) {
                $options['json'] = $json;
            }
            if ($query !== []) {
                $options['query'] = $query;
            }

            $response = $this->client->chain($endpoint)->{$method}($options);

            $body = $response->getBody()->getContents();
            throw_if($response->getStatusCode() >= 400, WePayException::fromWechatResponse(
                $body,
                $response->getStatusCode(),
            ));

            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
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
            'notify_url' => data_get($this->config, 'notify_url', ''),
            'amount' => [
                'total' => (int) $params['amount'],
                'currency' => 'CNY',
            ],
        ];

        return match ($this->type) {
            'js' => [...$body, 'payer' => ['openid' => $params['openid']]],
            'h5' => [
                ...$body,
                'scene_info' => [
                    'payer_client_ip' => data_get($params, 'client_ip', request()->ip()),
                    'h5_info' => ['type' => 'Wap'],
                ],
            ],
            default => $body,
        };
    }

    protected function formatPaymentResult(array $data): array
    {
        $base = ['success' => true, 'type' => $this->type];

        return match ($this->type) {
            'js' => [
                ...$base,
                'js_config' => $this->crypto->signPayData([
                    'appId' => (string) data_get($this->config, 'app_id', ''),
                    'timeStamp' => (string) now()->timestamp,
                    'nonceStr' => Str::random(32),
                    'package' => 'prepay_id='.(string) data_get($data, 'prepay_id', ''),
                    'signType' => 'RSA',
                ]),
            ],
            'app' => [
                ...$base,
                'app_config' => $this->crypto->signPayData([
                    'appid' => (string) data_get($this->config, 'app_id', ''),
                    'partnerid' => (string) data_get($this->config, 'mch_id', ''),
                    'prepayid' => (string) data_get($data, 'prepay_id', ''),
                    'package' => 'Sign=WXPay',
                    'noncestr' => Str::random(32),
                    'timestamp' => (string) now()->timestamp,
                ]),
            ],
            'native' => [
                ...$base,
                'code_url' => (string) data_get($data, 'code_url', ''),
                'qr_code' => filled(data_get($data, 'code_url'))
                    ? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='
                    .urlencode((string) $data['code_url'])
                    : '',
            ],
            'h5' => [...$base, 'h5_url' => (string) data_get($data, 'h5_url', '')],
            default => $base,
        };
    }

    /**
     * @throws WePayException
     */
    protected function ensureConfigIsValid(): void
    {
        $keys = ['app_id', 'mch_id', 'key', 'private_key_path'];
        foreach ($keys as $k) {
            throw_if(blank(data_get($this->config, $k)), WePayException::configError("微信支付配置缺失: {$k}"));
        }

        if (! str_starts_with((string) $this->config['private_key_path'], '/')) {
            $this->config['private_key_path'] = base_path((string) $this->config['private_key_path']);
        }
    }

    /**
     * @throws WePayException
     */
    protected function validateOrderParams(array $params): void
    {
        throw_if(
            blank(data_get($params, 'out_trade_no')) || (int) data_get($params, 'amount', 0) <= 0,
            WePayException::configError('下单参数不完整'),
        );
        throw_if(
            $this->type === 'js' && blank(data_get($params, 'openid')),
            WePayException::configError('JSAPI 支付缺少 openid'),
        );
    }
}

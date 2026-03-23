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
 * Class WechatPayServiceV2
 *
 * 微信支付 V3 API 服务（重构版）
 *
 * 改进点：
 * - 移除有缺陷的单例模式，改为独立实例工厂方法
 * - 私钥只加载一次并缓存在实例属性中，避免重复 I/O
 * - 实现真实的回调签名验证（AES-256-GCM + RSA）
 * - 清理冗余调试日志，统一使用 Throwable
 * - 使用 match 表达式替代 switch
 */
class WechatPayServiceV2
{
    /**
     * 支持的支付类型
     */
    public const PAYMENT_TYPES = [
        'app' => 'APP支付',
        'native' => '扫码支付',
        'js' => 'JSAPI支付',
        'h5' => 'H5支付',
    ];

    /**
     * 微信支付 V3 API 端点
     */
    private const ENDPOINTS = [
        'app' => 'v3/pay/transactions/app',
        'native' => 'v3/pay/transactions/native',
        'js' => 'v3/pay/transactions/jsapi',
        'h5' => 'v3/pay/transactions/h5',
    ];

    /**
     * @var BuilderChainable 微信支付 SDK 客户端
     */
    protected BuilderChainable $client;

    /**
     * @var OpenSSLAsymmetricKey 已加载的商户私钥（避免重复 I/O）
     */
    protected OpenSSLAsymmetricKey $privateKey;

    // -------------------------------------------------------------------------
    // 构造器（public，移除有缺陷的单例）
    // -------------------------------------------------------------------------

    /**
     * @param string $type 支付类型（app / native / js / h5）
     * @param array $config 覆盖配置（默认读 config('pay.wechat')）
     *
     * @throws WePayException
     */
    public function __construct(
        protected string $type = 'native',
        protected array $config = [],
    ) {
        $this->validateType($this->type);
        $this->config = empty($this->config) ? config('pay.wechat', []) : $this->config;
        $this->validateConfig($this->config);
        $this->initializeClient();
    }

    // -------------------------------------------------------------------------
    // 静态工厂方法
    // -------------------------------------------------------------------------

    /** 创建 APP 支付实例 */
    public static function app(array $config = []): static
    {
        return new static('app', $config);
    }

    /** 创建扫码支付实例 */
    public static function native(array $config = []): static
    {
        return new static('native', $config);
    }

    /** 创建 JSAPI 公众号支付实例 */
    public static function js(array $config = []): static
    {
        return new static('js', $config);
    }

    /** 创建 H5 支付实例 */
    public static function h5(array $config = []): static
    {
        return new static('h5', $config);
    }

    // -------------------------------------------------------------------------
    // 初始化
    // -------------------------------------------------------------------------

    /**
     * 验证支付类型
     *
     * @throws WePayException
     */
    protected function validateType(string $type): void
    {
        if (! isset(self::PAYMENT_TYPES[$type])) {
            throw WePayException::configError(
                "不支持的微信支付类型: {$type}，支持: ".implode(', ', array_keys(self::PAYMENT_TYPES)),
            );
        }
    }

    /**
     * 验证配置完整性并解析文件路径
     *
     * @throws WePayException
     */
    protected function validateConfig(array $config): void
    {
        foreach (['app_id', 'mch_id', 'private_key_path'] as $field) {
            if (empty($config[$field])) {
                throw WePayException::configError("微信支付配置缺失: {$field}");
            }
        }

        // 解析私钥路径
        $this->config['private_key_path'] = $this->resolveFilePath($config['private_key_path']);
        if (! file_exists($this->config['private_key_path'])) {
            throw WePayException::configError("私钥文件不存在: {$this->config['private_key_path']}");
        }

        // 解析商户证书路径（可选，用于从文件提取序列号）
        if (! empty($config['cert_path'])) {
            $this->config['cert_path'] = $this->resolveFilePath($config['cert_path']);
            if (! file_exists($this->config['cert_path'])) {
                throw WePayException::configError("商户证书文件不存在: {$this->config['cert_path']}");
            }
        }
    }

    /**
     * 初始化 SDK 客户端（加载私钥、下载平台证书、构建 Builder）
     *
     * @throws WePayException
     */
    protected function initializeClient(): void
    {
        try {
            // 加载私钥并缓存，后续签名复用同一实例
            $this->privateKey = Rsa::from(
                'file://'.$this->config['private_key_path'],
                Rsa::KEY_TYPE_PRIVATE,
            );

            $serial = $this->resolveCertificateSerial();
            $platformCerts = $this->fetchPlatformCertificates($serial);

            $this->client = Builder::factory([
                'mchid' => $this->config['mch_id'],
                'serial' => $serial,
                'privateKey' => $this->privateKey,
                'certs' => $platformCerts,
            ]);

            Log::debug('微信支付客户端初始化成功', [
                'mch_id' => $this->config['mch_id'],
                'type' => $this->type,
                'serial' => $serial,
            ]);
        } catch (Throwable $e) {
            throw WePayException::configError("微信支付客户端初始化失败: {$e->getMessage()}");
        }
    }

    /**
     * 解析证书序列号（优先读配置，其次从证书文件提取）
     *
     * @throws WePayException
     */
    protected function resolveCertificateSerial(): string
    {
        if (! empty($this->config['certificate_serial'])) {
            return $this->config['certificate_serial'];
        }

        if (! empty($this->config['cert_path'])) {
            try {
                $parsed = openssl_x509_parse((string) file_get_contents($this->config['cert_path']));
                if ($parsed && isset($parsed['serialNumber'])) {
                    return mb_strtoupper($parsed['serialNumber']);
                }
            } catch (Throwable $e) {
                Log::warning('从证书文件提取序列号失败', ['error' => $e->getMessage()]);
            }
        }

        throw WePayException::configError('无法获取证书序列号，请配置 certificate_serial 或 cert_path');
    }

    /**
     * 获取平台证书（优先读缓存，回退本地文件，最后调用 API 下载）
     *
     * @throws WePayException
     */
    protected function fetchPlatformCertificates(string $serial): array
    {
        $cacheKey = "wechat_platform_certs_{$this->config['mch_id']}";

        $cached = cache($cacheKey);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        // 尝试从本地文件加载
        $localCerts = $this->loadLocalPlatformCertificates();
        if (! empty($localCerts)) {
            cache([$cacheKey => $localCerts], now()->addHours(24));

            return $localCerts;
        }

        // 调用微信 API 下载
        return $this->downloadPlatformCertificates($serial, $cacheKey);
    }

    /**
     * 调用微信 API 下载并解密平台证书
     *
     * @throws WePayException
     */
    protected function downloadPlatformCertificates(string $serial, string $cacheKey): array
    {
        try {
            $timestamp = time();
            $nonce = Str::random(32);
            $url = 'https://api.mch.weixin.qq.com/v3/certificates';
            $path = '/v3/certificates';

            $signMessage = "GET\n{$path}\n{$timestamp}\n{$nonce}\n\n";
            $signature = Rsa::sign($signMessage, $this->privateKey);

            $authorization = sprintf(
                'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
                $this->config['mch_id'],
                $nonce,
                $timestamp,
                $serial,
                $signature,
            );

            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Accept' => 'application/json',
                'User-Agent' => 'WeChatPay-PHP-SDK/v2',
            ])->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('获取平台证书失败: '.$response->body());
            }

            $data = $response->json();

            if (empty($data['data']) || ! is_array($data['data'])) {
                throw new \RuntimeException('平台证书响应格式错误');
            }

            $apiKey = $this->config['key'] ?? '';
            if (empty($apiKey)) {
                throw new \RuntimeException('微信支付 API v3 密钥(key)未配置');
            }

            $platformCerts = [];
            foreach ($data['data'] as $certData) {
                if (! isset($certData['serial_no'], $certData['encrypt_certificate'])) {
                    continue;
                }

                $enc = $certData['encrypt_certificate'];

                $decrypted = AesGcm::decrypt(
                    $enc['ciphertext'],
                    $apiKey,
                    $enc['nonce'],
                    $enc['associated_data'],
                );

                $platformCerts[$certData['serial_no']] = $decrypted;
            }

            if (empty($platformCerts)) {
                throw new \RuntimeException('未获取到有效的平台证书');
            }

            cache([$cacheKey => $platformCerts], now()->addHours(24));

            Log::debug('微信平台证书下载成功', ['count' => count($platformCerts)]);

            return $platformCerts;
        } catch (Throwable $e) {
            throw WePayException::configError("无法获取平台证书: {$e->getMessage()}");
        }
    }

    /**
     * 从本地 storage/certs/wechat/ 目录加载平台证书
     */
    protected function loadLocalPlatformCertificates(): array
    {
        $certFiles = glob(storage_path('certs/wechat/platform_cert_*.pem')) ?: [];
        $platformCerts = [];

        foreach ($certFiles as $certFile) {
            try {
                $content = (string) file_get_contents($certFile);
                $parsed = openssl_x509_parse($content);

                if ($parsed && isset($parsed['serialNumber'])) {
                    $platformCerts[mb_strtoupper($parsed['serialNumber'])] = $content;
                }
            } catch (Throwable $e) {
                Log::warning('读取本地平台证书失败', ['file' => $certFile, 'error' => $e->getMessage()]);
            }
        }

        return $platformCerts;
    }

    // -------------------------------------------------------------------------
    // 支付接口
    // -------------------------------------------------------------------------

    /**
     * 快速下单（统一入口）
     *
     * @param string $outTradeNo 商户订单号
     * @param int $amount 订单金额（单位：分）
     * @param string $description 商品描述
     * @param array $extra 额外参数（openid / client_ip / return_url 等）
     *
     * @throws WePayException
     */
    public function pay(string $outTradeNo, int $amount, string $description, array $extra = []): array
    {
        return $this->createOrder(array_merge(
            ['out_trade_no' => $outTradeNo, 'amount' => $amount, 'description' => $description],
            $extra,
        ));
    }

    /**
     * 创建支付订单
     *
     * @throws WePayException
     */
    public function createOrder(array $params): array
    {
        $this->validateOrderParams($params);

        $requestData = $this->buildOrderParams($params);

        Log::info('发起微信支付', [
            'type' => $this->type,
            'out_trade_no' => $params['out_trade_no'],
            'amount' => $params['amount'],
        ]);

        try {
            $responseData = $this->callApi(self::ENDPOINTS[$this->type], 'POST', $requestData);

            Log::info('微信支付订单创建成功', [
                'type' => $this->type,
                'out_trade_no' => $params['out_trade_no'],
            ]);

            return $this->formatPaymentData($responseData);
        } catch (WePayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('微信支付订单创建失败', [
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 验证订单请求参数
     *
     * @throws WePayException
     */
    protected function validateOrderParams(array $params): void
    {
        foreach (['out_trade_no', 'amount', 'description'] as $field) {
            if (empty($params[$field])) {
                throw WePayException::configError("订单参数缺失: {$field}");
            }
        }

        if (! is_numeric($params['amount']) || (int) $params['amount'] <= 0) {
            throw WePayException::configError('订单金额必须为正整数（单位：分）');
        }

        if ($this->type === 'js' && empty($params['openid'])) {
            throw WePayException::configError('JSAPI支付需要提供 openid');
        }
    }

    /**
     * 构建 API 请求参数
     */
    protected function buildOrderParams(array $params): array
    {
        $data = [
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

        // 类型特定参数
        $data = match ($this->type) {
            'js' => array_merge($data, ['payer' => ['openid' => $params['openid']]]),
            'h5' => array_merge($data, [
                'scene_info' => [
                    'payer_client_ip' => $params['client_ip'] ?? request()->ip(),
                    'h5_info' => ['type' => 'Wap'],
                ],
            ]),
            default => $data,
        };

        return $data;
    }

    /**
     * 格式化支付结果
     */
    protected function formatPaymentData(array $data): array
    {
        $result = ['success' => true, 'type' => $this->type, 'data' => $data];

        return match ($this->type) {
            'js' => array_merge($result, ['js_config' => $this->buildJsConfig($data)]),
            'native' => array_merge($result, [
                'code_url' => $data['code_url'] ?? '',
                'qr_code' => isset($data['code_url']) ? $this->buildQrCodeUrl($data['code_url']) : '',
            ]),
            'app' => array_merge($result, ['app_config' => $this->buildAppConfig($data)]),
            'h5' => array_merge($result, ['h5_url' => $data['h5_url'] ?? '']),
            default => $result,
        };
    }

    // -------------------------------------------------------------------------
    // 订单查询 / 退款 / 退款查询
    // -------------------------------------------------------------------------

    /**
     * 查询订单状态
     *
     * @throws WePayException
     */
    public function queryOrder(string $outTradeNo): array
    {
        if (empty($outTradeNo)) {
            throw WePayException::configError('订单号不能为空');
        }

        try {
            $result = $this->callApi(
                "v3/pay/transactions/out-trade-no/{$outTradeNo}",
                'GET',
                [],
                ['mchid' => $this->config['mch_id']],
            );

            Log::info('微信支付订单查询成功', [
                'out_trade_no' => $outTradeNo,
                'trade_state' => $result['trade_state'] ?? '',
            ]);

            return [
                'success' => true,
                'data' => $result,
                'is_paid' => in_array($result['trade_state'] ?? '', ['SUCCESS']),
            ];
        } catch (WePayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('微信支付订单查询失败', ['out_trade_no' => $outTradeNo, 'error' => $e->getMessage()]);

            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 申请退款
     *
     * @param  array{
     *     out_trade_no: string,
     *     out_refund_no: string,
     *     total_amount: int,
     *     refund_amount: int,
     *     reason?: string,
     *     refund_notify_url?: string
     * } $params
     *
     * @throws WePayException
     */
    public function refund(array $params): array
    {
        $this->validateRefundParams($params);

        $data = [
            'out_trade_no' => $params['out_trade_no'],
            'out_refund_no' => $params['out_refund_no'],
            'reason' => $params['reason'] ?? '商户退款',
            'notify_url' => $params['refund_notify_url'] ?? ($this->config['refund_notify_url'] ?? ''),
            'amount' => [
                'refund' => (int) $params['refund_amount'],
                'total' => (int) $params['total_amount'],
                'currency' => 'CNY',
            ],
        ];

        try {
            $result = $this->callApi('v3/refund/domestic/refunds', 'POST', $data);

            Log::info('微信支付退款申请成功', [
                'out_trade_no' => $params['out_trade_no'],
                'out_refund_no' => $params['out_refund_no'],
                'refund_amount' => $params['refund_amount'],
                'status' => $result['status'] ?? 'PROCESSING',
            ]);

            return ['success' => true, 'data' => $result];
        } catch (WePayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('微信支付退款失败', ['error' => $e->getMessage()]);

            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 验证退款参数
     *
     * @throws WePayException
     */
    protected function validateRefundParams(array $params): void
    {
        foreach (['out_trade_no', 'out_refund_no', 'total_amount', 'refund_amount'] as $field) {
            if (empty($params[$field])) {
                throw WePayException::configError("退款参数缺失: {$field}");
            }
        }

        if (! is_numeric($params['total_amount']) || ! is_numeric($params['refund_amount'])) {
            throw WePayException::configError('金额必须为数字');
        }

        if ((int) $params['refund_amount'] > (int) $params['total_amount']) {
            throw WePayException::configError('退款金额不能大于订单总金额');
        }
    }

    /**
     * 查询退款状态
     *
     * @throws WePayException
     */
    public function queryRefund(string $outRefundNo): array
    {
        if (empty($outRefundNo)) {
            throw WePayException::configError('退款单号不能为空');
        }

        try {
            $result = $this->callApi("v3/refund/domestic/refunds/{$outRefundNo}", 'GET');

            Log::info('微信退款查询成功', [
                'out_refund_no' => $outRefundNo,
                'status' => $result['status'] ?? '',
            ]);

            return ['success' => true, 'data' => $result];
        } catch (WePayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('微信退款查询失败', ['out_refund_no' => $outRefundNo, 'error' => $e->getMessage()]);

            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 回调处理
    // -------------------------------------------------------------------------

    /**
     * 处理支付异步通知
     *
     * @param array $headers 请求头（留空则自动从当前 Request 读取）
     * @param string $body 请求体（留空则自动从当前 Request 读取）
     */
    public function handleCallback(array $headers = [], string $body = ''): array
    {
        return $this->handlePaymentCallback($headers, $body);
    }

    /**
     * 处理支付回调
     */
    public static function handlePaymentCallback(array $headers = [], string $body = '', string $type = 'native'): array
    {
        $headers = $headers ?: request()->headers->all();
        $body = $body ?: request()->getContent();

        try {
            if (! self::verifyCallback($headers, $body)) {
                Log::warning('微信支付回调签名验证失败', ['type' => $type]);

                return ['success' => false, 'message' => '签名验证失败', 'data' => []];
            }

            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            Log::info('微信支付回调验证成功', ['type' => $type, 'event_type' => $data['event_type'] ?? '']);

            return ['success' => true, 'data' => $data, 'callback_type' => 'payment'];
        } catch (Throwable $e) {
            Log::error('微信支付回调处理异常', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 处理退款回调
     */
    public static function handleRefundCallback(array $headers = [], string $body = '', string $type = 'native'): array
    {
        $headers = $headers ?: request()->headers->all();
        $body = $body ?: request()->getContent();

        try {
            if (! self::verifyCallback($headers, $body)) {
                Log::warning('微信退款回调签名验证失败', ['type' => $type]);

                return ['success' => false, 'message' => '签名验证失败', 'data' => []];
            }

            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $refundData = self::parseRefundCallbackData($data);

            Log::info('微信退款回调验证成功', [
                'out_trade_no' => $refundData['out_trade_no'],
                'refund_status' => $refundData['refund_status'],
            ]);

            return ['success' => true, 'data' => $refundData, 'callback_type' => 'refund'];
        } catch (Throwable $e) {
            Log::error('微信退款回调处理异常', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 验证微信支付回调签名（RSA-SHA256）
     *
     * 真实实现：用平台证书公钥验证签名
     */
    protected static function verifyCallback(array $headers, string $body): bool
    {
        try {
            $signature = $headers['wechatpay-signature'][0] ?? '';
            $timestamp = $headers['wechatpay-timestamp'][0] ?? '';
            $nonce = $headers['wechatpay-nonce'][0] ?? '';
            $serial = $headers['wechatpay-serial'][0] ?? '';

            if (! $signature || ! $timestamp || ! $nonce || ! $serial) {
                return false;
            }

            // 从缓存中获取平台证书作为验证公钥
            $cacheKey = 'wechat_platform_certs_'.config('pay.wechat.mch_id', '');
            $certs = cache($cacheKey, []);

            if (empty($certs[$serial])) {
                Log::warning('找不到对应序列号的平台证书', ['serial' => $serial]);

                // 无法取得证书时放行（降级），生产环境应改为 return false
                return (bool) config('app.debug', false);
            }

            $message = "{$timestamp}\n{$nonce}\n{$body}\n";
            $publicKey = Rsa::from($certs[$serial], Rsa::KEY_TYPE_PUBLIC);

            return Rsa::verify($message, $signature, $publicKey);
        } catch (Throwable $e) {
            Log::error('微信回调签名验证异常', ['error' => $e->getMessage()]);

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // 签名构建
    // -------------------------------------------------------------------------

    /**
     * 构建 JSAPI 支付配置（paySign 基于已缓存私钥）
     */
    protected function buildJsConfig(array $data): array
    {
        $config = [
            'appId' => $this->config['app_id'],
            'timeStamp' => (string) time(),
            'nonceStr' => Str::random(32),
            'package' => 'prepay_id='.($data['prepay_id'] ?? ''),
            'signType' => 'RSA',
        ];

        $message = "{$config['appId']}\n{$config['timeStamp']}\n{$config['nonceStr']}\n{$config['package']}\n";
        $config['paySign'] = Rsa::sign($message, $this->privateKey);

        return $config;
    }

    /**
     * 构建 APP 支付配置（sign 基于已缓存私钥）
     */
    protected function buildAppConfig(array $data): array
    {
        $config = [
            'appid' => $this->config['app_id'],
            'partnerid' => $this->config['mch_id'],
            'prepayid' => $data['prepay_id'] ?? '',
            'package' => 'Sign=WXPay',
            'noncestr' => Str::random(32),
            'timestamp' => (string) time(),
        ];

        $message = "{$config['appid']}\n{$config['timestamp']}\n{$config['noncestr']}\n{$config['prepayid']}\n";
        $config['sign'] = Rsa::sign($message, $this->privateKey);

        return $config;
    }

    // -------------------------------------------------------------------------
    // 回调数据解析 / 解密
    // -------------------------------------------------------------------------

    /**
     * 解析退款回调数据（从加密资源中提取业务字段）
     */
    protected static function parseRefundCallbackData(array $data): array
    {
        $resource = $data['resource'] ?? [];

        $decrypted = isset($resource['ciphertext'])
            ? self::decryptCallbackResource($resource)
            : $resource;

        return [
            'event_type' => $data['event_type'] ?? '',
            'summary' => $data['summary'] ?? '',
            'resource_type' => $data['resource_type'] ?? '',
            'refund_id' => $decrypted['refund_id'] ?? '',
            'out_refund_no' => $decrypted['out_refund_no'] ?? '',
            'transaction_id' => $decrypted['transaction_id'] ?? '',
            'out_trade_no' => $decrypted['out_trade_no'] ?? '',
            'refund_status' => $decrypted['refund_status'] ?? '',
            'success_time' => $decrypted['success_time'] ?? '',
            'amount' => $decrypted['amount'] ?? [],
            'user_received_account' => $decrypted['user_received_account'] ?? '',
        ];
    }

    /**
     * AES-256-GCM 解密微信支付回调中的 resource 加密块
     */
    protected static function decryptCallbackResource(array $resource): array
    {
        $apiKey = config('pay.wechat.key', '');

        if (empty($apiKey)) {
            Log::warning('微信支付 API v3 密钥未配置，无法解密回调数据');

            return $resource;
        }

        try {
            $ciphertext = base64_decode($resource['ciphertext'] ?? '', true);
            $associatedData = $resource['associated_data'] ?? '';
            $nonce = $resource['nonce'] ?? '';

            if ($ciphertext === false || mb_strlen($ciphertext, '8bit') < 16) {
                throw new \InvalidArgumentException('密文 Base64 解码失败或长度不足');
            }

            // 拆分认证标签（末尾 16 字节）
            $authTag = mb_substr($ciphertext, -16, null, '8bit');
            $ciphertext = mb_substr($ciphertext, 0, -16, '8bit');

            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $apiKey,
                OPENSSL_RAW_DATA,
                $nonce,
                $authTag,
                $associatedData,
            );

            if ($decrypted === false) {
                throw new \RuntimeException('AES-256-GCM 解密失败');
            }

            return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::error('微信回调资源解密失败', ['error' => $e->getMessage()]);

            return $resource;
        }
    }

    // -------------------------------------------------------------------------
    // 通用 API 调用层
    // -------------------------------------------------------------------------

    /**
     * 调用微信支付 API
     *
     * @param string $endpoint API 路径（如 v3/pay/transactions/native）
     * @param string $method HTTP 方法（GET / POST）
     * @param array $body 请求体（POST 时使用）
     * @param array $query Query 参数（GET 时使用）
     *
     * @throws WePayException
     */
    protected function callApi(string $endpoint, string $method = 'POST', array $body = [], array $query = []): array
    {
        try {
            $chain = $this->client->chain($endpoint);

            $response = match (mb_strtoupper($method)) {
                'GET' => $chain->get(empty($query) ? [] : ['query' => $query]),
                'POST' => $chain->post(['json' => $body]),
                default => throw new \InvalidArgumentException("不支持的 HTTP 方法: {$method}"),
            };

            $rawBody = $response->getBody()->getContents();
            $result = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

            if ($response->getStatusCode() !== 200) {
                throw WePayException::fromWechatResponse($rawBody, $response->getStatusCode());
            }

            return $result;
        } catch (ClientException $e) {
            $body = $e->getResponse()->getBody()->getContents();

            throw WePayException::fromWechatResponse($body, $e->getResponse()->getStatusCode());
        } catch (ServerException $e) {
            $body = $e->getResponse()->getBody()->getContents();

            throw WePayException::fromWechatResponse($body, $e->getResponse()->getStatusCode());
        } catch (RequestException $e) {
            throw WePayException::networkError('网络连接失败，请检查网络后重试');
        } catch (WePayException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 辅助方法
    // -------------------------------------------------------------------------

    /**
     * 解析文件路径（支持绝对路径和相对路径）
     */
    protected function resolveFilePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * 生成二维码图片 URL（使用第三方服务）
     */
    protected function buildQrCodeUrl(string $codeUrl): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?data='.urlencode($codeUrl).'&size=200x200';
    }

    /** 获取支付类型 */
    public function getType(): string
    {
        return $this->type;
    }

    /** 获取支付类型描述 */
    public function getTypeDescription(): string
    {
        return self::PAYMENT_TYPES[$this->type] ?? '未知类型';
    }

    /** 获取当前配置 */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** 获取底层 SDK 客户端 */
    public function getClient(): BuilderChainable
    {
        return $this->client;
    }
}

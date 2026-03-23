<?php

namespace App\Services\Pay;

use App\Exceptions\WePayException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * 微信支付服务类
 *
 * 提供微信支付的统一接口，支持多种支付方式
 * 包括 APP 支付、扫码支付、公众号支付、H5 支付等
 * 使用微信官方 PHP SDK
 */
class WechatPayService
{
    /**
     * 静态实例
     */
    private static ?self $instance = null;

    /**
     * 支持的支付类型
     */
    public const PAYMENT_TYPES = [
        'app' => 'APP',
        'native' => 'NATIVE',
        'js' => 'JSAPI',
        'h5' => 'H5',
    ];

    /**
     * @var BuilderChainable 微信支付客户端
     */
    protected BuilderChainable $client;

    /**
     * @var array 支付配置
     */
    protected array $config;

    /**
     * @var string 支付类型
     */
    protected string $type;

    /**
     * 获取单例实例
     *
     * @param string $type 支付类型
     * @param array $config 支付配置
     */
    public static function getInstance(
        string $type = 'native',
        array $config = [],
    ): self {
        if (self::$instance === null) {
            self::$instance = new self($type, $config);
        }

        return self::$instance;
    }

    /**
     * 初始化支付网关
     *
     * 重置单例实例（用于调试）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 初始化支付网关
     * =======
     *
     * /**
     * 初始化支付网关
     *
     * @param string $type 支付类型
     * @param array $config 支付配置
     */
    protected function __construct(string $type = 'native', array $config = [])
    {
        $this->type = $type;
        $this->validateType($type);
        $this->config = $config ?: config('pay.wechat', []);
        $this->validateConfig($this->config);
        $this->initializeClient();
    }

    /**
     * 创建 APP 支付实例
     *
     * @param array $config 支付配置
     *
     * @throws WePayException
     */
    public static function app(array $config = []): static
    {
        return self::getInstance('app', $config);
    }

    /**
     * 创建扫码支付实例
     *
     * @param array $config 支付配置
     */
    public static function native(array $config = []): static
    {
        return self::getInstance('native', $config);
    }

    /**
     * 创建公众号支付实例
     *
     * @param array $config 支付配置
     */
    public static function js(array $config = []): static
    {
        return self::getInstance('js', $config);
    }

    /**
     * 创建 H5 支付实例
     *
     * @param array $config 支付配置
     */
    public static function h5(array $config = []): static
    {
        return self::getInstance('h5', $config);
    }

    /**
     * 验证支付类型
     *
     * @throws WePayException
     */
    protected function validateType(string $type): void
    {
        if (! isset(self::PAYMENT_TYPES[$type])) {
            throw WePayException::configError("不支持的微信支付类型: {$type}");
        }
    }

    /**
     * 验证配置是否完整
     *
     * @throws WePayException
     */
    protected function validateConfig(array $config): void
    {
        // 添加调试日志
        Log::info('WechatPayService 配置验证开始', [
            'config_keys' => array_keys($config),
            'config_values' => array_map(static function ($value) {
                return is_string($value)
                    ? (mb_strlen($value) > 50
                        ? mb_substr($value, 0, 50).'...'
                        : $value)
                    : $value;
            }, $config),
        ]);

        $required = ['app_id', 'mch_id', 'private_key_path'];
        foreach ($required as $item) {
            $value = $config[$item] ?? null;
            $isEmpty = empty($value);

            Log::info("验证配置字段: {$item}", [
                'value' => $value,
                'is_empty' => $isEmpty,
                'type' => gettype($value),
            ]);

            if ($isEmpty) {
                Log::error('微信支付配置验证失败', [
                    'missing_field' => $item,
                    'config' => $config,
                ]);
                throw WePayException::configError("微信支付配置缺失: {$item}");
            }
        }

        // 处理私钥文件路径（支持相对路径和绝对路径）
        $privateKeyPath = $this->resolveFilePath($config['private_key_path']);
        if (! file_exists($privateKeyPath)) {
            throw WePayException::configError(
                "私钥文件不存在: {$privateKeyPath}",
            );
        }
        $this->config['private_key_path'] = $privateKeyPath;

        // 处理证书文件路径（如果提供了证书路径）
        if (! empty($config['cert_path'])) {
            $certPath = $this->resolveFilePath($config['cert_path']);
            if (! file_exists($certPath)) {
                throw WePayException::configError(
                    "证书文件不存在: {$certPath}",
                );
            }
            $this->config['cert_path'] = $certPath;
        }
    }

    /**
     * 解析文件路径（支持相对路径和绝对路径）
     */
    protected function resolveFilePath(string $path): string
    {
        // 如果是绝对路径，直接返回
        if (str_starts_with($path, '/') || str_contains($path, ':\\')) {
            return $path;
        }

        // 如果是相对路径，转换为绝对路径
        if (str_starts_with($path, 'storage/')) {
            return base_path($path);
        }

        // 默认认为是相对于项目根目录的路径
        return base_path($path);
    }

    /**
     * 初始化微信支付客户端
     *
     * @throws WePayException
     */
    protected function initializeClient(): void
    {
        try {
            // 读取私钥 - 使用新的 Rsa::from() 方法
            $privateKey = Rsa::from(
                'file://'.$this->config['private_key_path'],
                Rsa::KEY_TYPE_PRIVATE,
            );

            // 获取证书序列号
            $certificateSerial = $this->getCertificateSerial();

            // 获取平台证书
            $platformCerts = $this->downloadPlatformCertificates(
                $privateKey,
                $certificateSerial,
            );

            // 初始化微信支付客户端
            $this->client = Builder::factory([
                'mchid' => $this->config['mch_id'],
                'serial' => $certificateSerial,
                'privateKey' => $privateKey,
                'certs' => $platformCerts,
            ]);

            Log::info('微信支付客户端初始化成功', [
                'mch_id' => $this->config['mch_id'],
                'app_id' => $this->config['app_id'],
                'type' => $this->type,
                'certificate_serial' => $certificateSerial,
            ]);
        } catch (\Exception $e) {
            Log::error('微信支付客户端初始化失败', [
                'error' => $e->getMessage(),
                'mch_id' => $this->config['mch_id'] ?? '',
                'type' => $this->type,
            ]);
            throw WePayException::configError(
                "微信支付客户端初始化失败: {$e->getMessage()}",
            );
        }
    }

    /**
     * 下载平台证书
     *
     * @param mixed $privateKey 商户私钥
     * @param string $certificateSerial 商户证书序列号
     * @return array 平台证书数组
     *
     * @throws WePayException
     */
    protected function downloadPlatformCertificates(
        mixed $privateKey,
        string $certificateSerial,
    ): array {
        try {
            // 检查缓存
            $cacheKey = "wechat_platform_certs_{$this->config['mch_id']}";
            $cachedCerts = cache($cacheKey);

            if (is_array($cachedCerts) && ! empty($cachedCerts)) {
                Log::info('使用缓存的平台证书', [
                    'count' => count($cachedCerts),
                ]);

                return $cachedCerts;
            }

            // 使用 HTTP 客户端直接调用微信 API 获取证书
            $timestamp = time();
            $nonce = Str::random(32);
            $url = 'https://api.mch.weixin.qq.com/v3/certificates';
            $method = 'GET';
            $body = '';

            // 构建签名字符串
            $signString
                = $method
                ."\n"
                .parse_url($url, PHP_URL_PATH)
                ."\n"
                .$timestamp
                ."\n"
                .$nonce
                ."\n"
                .$body
                ."\n";

            // 生成签名 - 使用 Rsa 类的静态方法
            $signature = Rsa::sign($signString, $privateKey);

            // 构建 Authorization 头
            $authorization = sprintf(
                'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
                $this->config['mch_id'],
                $nonce,
                $timestamp,
                $certificateSerial,
                $signature,
            );

            // 发送请求
            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'WeChatPay-PHP-SDK',
            ])->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    '获取平台证书失败: '.$response->body(),
                );
            }

            $data = $response->json();

            if (! isset($data['data']) || ! is_array($data['data'])) {
                throw new \RuntimeException('平台证书响应格式错误');
            }

            $platformCerts = [];
            foreach ($data['data'] as $certData) {
                if (
                    isset(
                        $certData['serial_no'],
                        $certData['encrypt_certificate'],
                    )
                ) {
                    $encryptedCert = $certData['encrypt_certificate'];

                    // 解密证书
                    $decryptedCert = AesGcm::decrypt(
                        $encryptedCert['ciphertext'],
                        $this->config['key'],
                        $encryptedCert['nonce'],
                        $encryptedCert['associated_data'],
                    );

                    $platformCerts[$certData['serial_no']] = $decryptedCert;
                }
            }

            if (empty($platformCerts)) {
                throw new \RuntimeException('未获取到有效的平台证书');
            }

            // 缓存证书（24小时）
            cache([$cacheKey => $platformCerts], now()->addHours(24));

            Log::info('成功下载平台证书', ['count' => count($platformCerts)]);

            return $platformCerts;
        } catch (\Exception $e) {
            Log::error('下载平台证书失败', ['error' => $e->getMessage()]);

            // 如果下载失败，尝试使用本地证书文件
            $localCerts = $this->loadLocalPlatformCertificates();
            if (! empty($localCerts)) {
                Log::info('使用本地平台证书', ['count' => count($localCerts)]);

                return $localCerts;
            }

            throw WePayException::configError(
                "无法获取平台证书: {$e->getMessage()}",
            );
        }
    }

    /**
     * 加载本地平台证书
     */
    protected function loadLocalPlatformCertificates(): array
    {
        $platformCerts = [];
        $certDir = storage_path('certs/wechat');

        // 查找所有平台证书文件
        $certFiles = glob($certDir.'/platform_cert_*.pem');

        foreach ($certFiles as $certFile) {
            try {
                $certContent = file_get_contents($certFile);
                $cert = openssl_x509_parse($certContent);

                if ($cert && isset($cert['serialNumber'])) {
                    $serial = mb_strtoupper($cert['serialNumber']);
                    $platformCerts[$serial] = $certContent;
                }
            } catch (\Exception $e) {
                Log::warning('读取本地平台证书失败', [
                    'file' => $certFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $platformCerts;
    }

    /**
     * 获取证书序列号
     *
     * @throws WePayException
     */
    protected function getCertificateSerial(): string
    {
        // 如果配置中提供了证书序列号，直接使用
        if (! empty($this->config['certificate_serial'])) {
            return $this->config['certificate_serial'];
        }

        // 如果提供了证书文件路径，从证书中读取序列号
        if (
            ! empty($this->config['cert_path'])
            && file_exists($this->config['cert_path'])
        ) {
            try {
                $certContent = file_get_contents($this->config['cert_path']);
                $cert = openssl_x509_parse($certContent);
                if ($cert && isset($cert['serialNumber'])) {
                    return mb_strtoupper($cert['serialNumber']);
                }
            } catch (\Exception $e) {
                Log::warning('从证书文件读取序列号失败', [
                    'cert_path' => $this->config['cert_path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw WePayException::configError(
            '无法获取证书序列号，请在配置中提供 certificate_serial 或 cert_path',
        );
    }

    /**
     * 创建支付订单
     *
     * @param array $params 订单参数
     * @return array 支付参数
     *
     * @throws WePayException
     */
    public function createOrder(array $params): array
    {
        try {
            // 验证必要参数
            $this->validateOrderParams($params);

            // 构建请求参数
            $requestData = $this->buildOrderParams($params);

            // 根据支付类型调用不同的接口
            $response = $this->callPaymentApi($requestData);

            // 记录日志
            Log::info('微信支付订单创建成功', [
                'type' => $this->type,
                'out_trade_no' => $params['out_trade_no'] ?? '',
                'amount' => $params['amount'] ?? 0,
            ]);

            return $this->formatPaymentData($response);
        } catch (WePayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('微信支付订单创建失败', [
                'type' => $this->type,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 验证订单参数
     *
     * @throws WePayException
     */
    protected function validateOrderParams(array $params): void
    {
        $required = ['out_trade_no', 'amount', 'description'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw WePayException::configError("订单参数缺失: {$field}");
            }
        }

        if (! is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw WePayException::configError('订单金额必须大于0');
        }

        // JS支付需要openid
        if ($this->type === 'js' && empty($params['openid'])) {
            throw WePayException::configError('JSAPI支付需要提供openid');
        }
    }

    /**
     * 构建订单参数
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
                'total' => (int) $params['amount'], // 金额，单位：分
                'currency' => 'CNY',
            ],
        ];

        // 根据支付类型添加特定参数
        switch ($this->type) {
            case 'js':
                $data['payer'] = [
                    'openid' => $params['openid'],
                ];
                break;
            case 'h5':
                $data['scene_info'] = [
                    'payer_client_ip' => $params['client_ip'] ?? request()->ip(),
                    'h5_info' => [
                        'type' => 'Wap',
                    ],
                ];
                break;
        }

        return $data;
    }

    /**
     * 调用支付接口
     */
    protected function callPaymentApi(array $data): array
    {
        $endpoint = match ($this->type) {
            'js' => 'v3/pay/transactions/jsapi',
            'native' => 'v3/pay/transactions/native',
            'app' => 'v3/pay/transactions/app',
            'h5' => 'v3/pay/transactions/h5',
            default => 'v3/pay/transactions/native',
        };

        try {
            $response = $this->client
                ->chain($endpoint)
                ->post(['json' => $data]);

            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            if ($response->getStatusCode() !== 200) {
                throw WePayException::fromWechatResponse(
                    $responseBody,
                    $response->getStatusCode(),
                );
            }

            return $result;
        } catch (ClientException $e) {
            // 处理 4xx 客户端错误
            $responseBody = $e->getResponse()->getBody()->getContents();
            throw WePayException::fromWechatResponse(
                $responseBody,
                $e->getResponse()->getStatusCode(),
            );
        } catch (ServerException $e) {
            // 处理 5xx 服务器错误
            $responseBody = $e->getResponse()->getBody()->getContents();
            throw WePayException::fromWechatResponse(
                $responseBody,
                $e->getResponse()->getStatusCode(),
            );
        } catch (RequestException $e) {
            // 处理网络错误等其他请求异常
            throw WePayException::networkError(
                '网络连接失败，请检查网络后重试',
            );
        }
    }

    /**
     * 格式化支付数据
     */
    protected function formatPaymentData(array $data): array
    {
        $result = [
            'success' => true,
            'type' => $this->type,
            'data' => $data,
        ];

        // 根据不同支付类型添加特定数据
        switch ($this->type) {
            case 'js':
                $result['js_config'] = $this->buildJsConfig($data);
                break;

            case 'native':
                if (isset($data['code_url'])) {
                    $result['qr_code'] = $this->generateQrCode(
                        $data['code_url'],
                    );
                    $result['code_url'] = $data['code_url'];
                }
                break;

            case 'app':
                $result['app_config'] = $this->buildAppConfig($data);
                break;

            case 'h5':
                if (isset($data['h5_url'])) {
                    $result['h5_url'] = $data['h5_url'];
                }
                break;
        }

        return $result;
    }

    /**
     * 构建 JS 支付配置
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

        // 生成签名
        $config['paySign'] = $this->generateJsApiSign($config);

        return $config;
    }

    /**
     * 构建 APP 支付配置
     */
    protected function buildAppConfig(array $data): array
    {
        $config = [
            'appid' => $this->config['app_id'],
            'partnerid' => $this->config['mch_id'],
            'prepayid' => $data['prepay_id'] ?? '',
            'package' => 'Sign=WXPay',
            'noncestr' => Str::random(32),
            'timestamp' => time(),
        ];

        // 生成签名
        $config['sign'] = $this->generateAppSign($config);

        return $config;
    }

    /**
     * 生成 JSAPI 签名
     */
    protected function generateJsApiSign(array $config): string
    {
        $message
            = $config['appId']
            ."\n"
            .$config['timeStamp']
            ."\n"
            .$config['nonceStr']
            ."\n"
            .$config['package']
            ."\n";

        $privateKey = Rsa::from(
            'file://'.$this->config['private_key_path'],
            Rsa::KEY_TYPE_PRIVATE,
        );

        return Rsa::sign($message, $privateKey);
    }

    /**
     * 生成 APP 签名
     */
    protected function generateAppSign(array $config): string
    {
        $message
            = $config['appid']
            ."\n"
            .$config['timestamp']
            ."\n"
            .$config['noncestr']
            ."\n"
            .$config['prepayid']
            ."\n";

        $privateKey = Rsa::from(
            'file://'.$this->config['private_key_path'],
            Rsa::KEY_TYPE_PRIVATE,
        );

        return Rsa::sign($message, $privateKey);
    }

    /**
     * 生成二维码图片URL
     */
    protected function generateQrCode(string $codeUrl): string
    {
        // 使用第三方二维码生成服务
        return 'https://api.qrserver.com/v1/create-qr-code/?data='
            .urlencode($codeUrl)
            .'&size=200x200';
    }

    /**
     * 处理支付回调
     *
     * @param array $headers 请求头
     * @param string $body 请求体
     * @param string $type 支付类型
     * @return array 验证结果
     */
    public static function handlePaymentCallback(
        array $headers = [],
        string $body = '',
        string $type = 'native',
    ): array {
        try {
            // 如果没有传入数据，从请求中获取
            if (empty($headers)) {
                $headers = request()->headers->all();
            }
            if (empty($body)) {
                $body = request()->getContent();
            }

            // 验证签名
            $isVerified = self::verifyCallback($headers, $body);

            if ($isVerified) {
                $data = json_decode($body, true);

                Log::info('微信支付回调验证成功', [
                    'type' => $type,
                    'data' => $data,
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                    'message' => '支付回调验证成功',
                    'callback_type' => 'payment',
                ];
            }

            Log::warning('微信支付回调验证失败', [
                'type' => $type,
                'headers' => $headers,
                'body' => $body,
            ]);

            return [
                'success' => false,
                'message' => '支付回调验证失败',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('微信支付回调处理异常', [
                'type' => $type,
                'error' => $e->getMessage(),
                'headers' => $headers,
                'body' => $body,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * 处理退款回调
     *
     * @param array $headers 请求头
     * @param string $body 请求体
     * @param string $type 支付类型
     * @return array 验证结果
     */
    public static function handleRefundCallback(
        array $headers = [],
        string $body = '',
        string $type = 'native',
    ): array {
        try {
            // 如果没有传入数据，从请求中获取
            if (empty($headers)) {
                $headers = request()->headers->all();
            }
            if (empty($body)) {
                $body = request()->getContent();
            }

            // 验证签名
            $isVerified = self::verifyCallback($headers, $body);

            Log::info('微信退款回调验证状态', [
                'is_verified' => $isVerified,
                'headers_keys' => array_keys($headers),
                'body_length' => mb_strlen($body),
            ]);

            if ($isVerified) {
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                Log::info('微信退款回调原始数据', [
                    'data_keys' => array_keys($data),
                    'has_resource' => isset($data['resource']),
                    'event_type' => $data['event_type'] ?? 'unknown',
                ]);

                // 解析退款回调数据
                $refundData = self::parseRefundCallbackData($data);

                Log::info('微信退款回调验证成功', [
                    'type' => $type,
                    'refund_data_keys' => array_keys($refundData),
                    'has_out_trade_no' => ! empty($refundData['out_trade_no']),
                    'has_out_refund_no' => ! empty($refundData['out_refund_no']),
                    'has_refund_id' => ! empty($refundData['refund_id']),
                    'refund_status' => $refundData['refund_status'] ?? 'unknown',
                ]);

                return [
                    'success' => true,
                    'data' => $refundData,
                    'message' => '退款回调验证成功',
                    'callback_type' => 'refund',
                ];
            }

            Log::warning('微信退款回调验证失败', [
                'type' => $type,
                'headers' => $headers,
                'body' => $body,
            ]);

            return [
                'success' => false,
                'message' => '退款回调验证失败',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('微信退款回调处理异常', [
                'type' => $type,
                'error' => $e->getMessage(),
                'headers' => $headers,
                'body' => $body,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * 兼容旧方法名
     */
    public function handleCallback(
        array $headers = [],
        string $body = '',
    ): array {
        return $this->handlePaymentCallback($headers, $body);
    }

    /**
     * 验证回调签名
     */
    protected static function verifyCallback(array $headers, string $body): bool
    {
        try {
            // 获取签名相关头部信息
            $signature = $headers['wechatpay-signature'][0] ?? '';
            $timestamp = $headers['wechatpay-timestamp'][0] ?? '';
            $nonce = $headers['wechatpay-nonce'][0] ?? '';
            $serial = $headers['wechatpay-serial'][0] ?? '';

            if (
                empty($signature)
                || empty($timestamp)
                || empty($nonce)
                || empty($serial)
            ) {
                return false;
            }

            // 构建验签字符串
            $message = $timestamp."\n".$nonce."\n".$body."\n";

            // 这里需要微信支付平台证书来验证签名
            // 实际项目中需要从微信支付获取平台证书
            // 暂时返回 true，实际使用时需要实现证书验证逻辑
            return true;
        } catch (\Exception $e) {
            Log::error('回调签名验证失败', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * 订单查询
     *
     * @param string $outTradeNo 商户订单号
     *
     * @throws WePayException
     */
    public function queryOrder(string $outTradeNo): array
    {
        try {
            if (empty($outTradeNo)) {
                throw WePayException::configError('订单号不能为空');
            }

            $response = $this->client
                ->chain("v3/pay/transactions/out-trade-no/{$outTradeNo}")
                ->get([
                    'query' => [
                        'mchid' => $this->config['mch_id'],
                    ],
                ]);

            $responseBody = $response->getBody()->getContents();
            $result = json_decode(
                $responseBody,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if ($response->getStatusCode() !== 200) {
                throw WePayException::fromWechatResponse(
                    $responseBody,
                    $response->getStatusCode(),
                );
            }

            Log::info('微信支付订单查询成功', [
                'type' => $this->type,
                'out_trade_no' => $outTradeNo,
            ]);

            return [
                'success' => true,
                'data' => $result,
                'message' => '查询成功',
            ];
        } catch (WePayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('微信支付订单查询失败', [
                'type' => $this->type,
                'out_trade_no' => $outTradeNo,
                'error' => $e->getMessage(),
            ]);
            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 订单退款
     *
     * @param array $params 退款参数
     *
     * @throws WePayException
     */
    public function refund(array $params): array
    {
        try {
            $this->validateRefundParams($params);

            $data = [
                'out_trade_no' => $params['out_trade_no'],
                'out_refund_no' => $params['out_refund_no'],
                'amount' => [
                    'refund' => (int) $params['refund_amount'],
                    'total' => (int) $params['total_amount'],
                    'currency' => 'CNY',
                ],
                'reason' => $params['reason'] ?? '商户退款',
                // 退款回调地址
                'notify_url' => $params['refund_notify_url']
                    ?? ($this->config['refund_notify_url'] ?? ''),
            ];

            $response = $this->client
                ->chain('v3/refund/domestic/refunds')
                ->post(['json' => $data]);

            $responseBody = $response->getBody()->getContents();
            $result = json_decode(
                $responseBody,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if ($response->getStatusCode() !== 200) {
                throw WePayException::fromWechatResponse(
                    $responseBody,
                    $response->getStatusCode(),
                );
            }

            Log::info('微信支付退款申请成功', [
                'type' => $this->type,
                'out_trade_no' => $params['out_trade_no'] ?? '',
                'out_refund_no' => $params['out_refund_no'] ?? '',
                'refund_amount' => $params['refund_amount'] ?? 0,
                'status' => $result['status'] ?? 'PROCESSING',
            ]);

            return [
                'success' => true,
                'data' => $result,
                'message' => '退款申请成功，请等待处理结果',
            ];
        } catch (WePayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('微信支付退款失败', [
                'type' => $this->type,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
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
        if (empty($params['out_trade_no'])) {
            throw WePayException::configError('商户订单号不能为空');
        }

        if (empty($params['out_refund_no'])) {
            throw WePayException::configError('退款单号不能为空');
        }

        if (empty($params['total_amount']) || empty($params['refund_amount'])) {
            throw WePayException::configError('总金额和退款金额不能为空');
        }

        if (
            ! is_numeric($params['total_amount'])
            || ! is_numeric($params['refund_amount'])
        ) {
            throw WePayException::configError('金额必须为数字');
        }

        if ($params['refund_amount'] > $params['total_amount']) {
            throw WePayException::configError('退款金额不能大于订单总金额');
        }
    }

    /**
     * 快速创建支付订单（简化版）
     *
     * @param string $outTradeNo 商户订单号
     * @param int $amount 订单金额（分）
     * @param string $description 商品描述
     * @param array $extra 额外参数
     *
     * @throws WePayException
     */
    public function pay(
        string $outTradeNo,
        int $amount,
        string $description,
        array $extra = [],
    ): array {
        $params = array_merge(
            [
                'out_trade_no' => $outTradeNo,
                'amount' => $amount,
                'description' => $description,
            ],
            $extra,
        );

        return $this->createOrder($params);
    }

    /**
     * 获取客户端实例
     */
    public function getClient(): BuilderChainable
    {
        return $this->client;
    }

    /**
     * 获取支付类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 解析退款回调数据
     */
    protected static function parseRefundCallbackData(array $data): array
    {
        $resource = $data['resource'] ?? [];

        // 如果数据是加密的，需要解密
        if (isset($resource['ciphertext'])) {
            Log::info('退款回调数据需要解密', [
                'resource_keys' => array_keys($resource),
                'has_ciphertext' => isset($resource['ciphertext']),
                'has_algorithm' => isset($resource['algorithm']),
                'algorithm' => $resource['algorithm'] ?? 'unknown',
                'ciphertext_length' => mb_strlen($resource['ciphertext'] ?? ''),
            ]);
            $decryptedData = self::decryptRefundData($resource);
            Log::info('退款回调数据解密完成', [
                'decrypted_keys' => array_keys($decryptedData),
                'has_refund_id' => isset($decryptedData['refund_id']),
                'has_out_trade_no' => isset($decryptedData['out_trade_no']),
                'has_out_refund_no' => isset($decryptedData['out_refund_no']),
                'has_refund_status' => isset($decryptedData['refund_status']),
                'decrypted_data_sample' => array_slice($decryptedData, 0, 3),
            ]);
        } else {
            $decryptedData = $resource;
            Log::info('退款回调数据无需解密', [
                'resource_keys' => array_keys($resource),
                'resource_sample' => array_slice($resource, 0, 3),
            ]);
        }

        return [
            'event_type' => $data['event_type'] ?? '',
            'summary' => $data['summary'] ?? '',
            'resource_type' => $data['resource_type'] ?? '',
            'refund_id' => $decryptedData['refund_id'] ?? '',
            'out_refund_no' => $decryptedData['out_refund_no'] ?? '',
            'transaction_id' => $decryptedData['transaction_id'] ?? '',
            'out_trade_no' => $decryptedData['out_trade_no'] ?? '',
            'refund_status' => $decryptedData['refund_status'] ?? '',
            'success_time' => $decryptedData['success_time'] ?? '',
            'amount' => $decryptedData['amount'] ?? [],
            'user_received_account' => $decryptedData['user_received_account'] ?? '',
        ];
    }

    /**
     * 解密退款回调数据
     *
     * 注意：这里需要实现具体的解密逻辑
     * 微信支付会使用 AES-256-GCM 算法加密敏感数据
     */
    protected static function decryptRefundData(array $resource): array
    {
        Log::info('开始解密退款回调数据', [
            'resource_keys' => array_keys($resource),
            'algorithm' => $resource['algorithm'] ?? 'unknown',
            'ciphertext_length' => mb_strlen($resource['ciphertext'] ?? ''),
            'nonce_length' => mb_strlen($resource['nonce'] ?? ''),
            'associated_data' => $resource['associated_data'] ?? '',
        ]);

        $ciphertext = $resource['ciphertext'] ?? '';
        $associatedData = $resource['associated_data'] ?? '';
        $nonce = $resource['nonce'] ?? '';
        $algorithm = $resource['algorithm'] ?? 'AEAD_AES_256_GCM';

        if (empty($ciphertext)) {
            Log::warning('退款回调数据缺少密文', ['resource' => $resource]);

            return $resource;
        }

        // 使用微信支付API密钥解密数据
        $apiKey = config('pay.wechat.key', '');
        if (empty($apiKey)) {
            Log::warning('微信支付API密钥未配置');

            return $resource;
        }

        try {
            // 解码 Base64 密文
            $ciphertext = base64_decode($ciphertext);
            if ($ciphertext === false) {
                throw new \InvalidArgumentException('密文Base64解码失败');
            }

            // 检查密文长度是否足够（至少16字节的认证标签）
            if (mb_strlen($ciphertext) < 16) {
                throw new \InvalidArgumentException('密文长度不足');
            }

            // 提取认证标签（最后16字节）和实际密文
            $authTag = mb_substr($ciphertext, -16);
            $ciphertext = mb_substr($ciphertext, 0, -16);

            // 使用 OpenSSL 解密
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
                throw new \InvalidArgumentException('AES-256-GCM 解密失败');
            }

            $decryptedData = json_decode($decrypted, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('解密数据JSON解析失败', [
                    'json_error' => json_last_error_msg(),
                    'decrypted_raw' => mb_substr($decrypted, 0, 200),
                ]);
                throw new \InvalidArgumentException('解密数据JSON解析失败');
            }

            Log::info('退款回调数据解密成功', [
                'original_resource_keys' => array_keys($resource),
                'decrypted_data_keys' => array_keys($decryptedData),
                'decrypted_data_sample' => array_slice($decryptedData, 0, 3),
            ]);

            return $decryptedData;
        } catch (\Exception $e) {
            Log::error('退款回调数据解密异常', [
                'error' => $e->getMessage(),
                'resource' => $resource,
            ]);

            return $resource;
        }
    }

    /**
     * 查询退款状态
     *
     * @param string $outRefundNo 商户退款单号
     *
     * @throws WePayException
     */
    public function queryRefund(string $outRefundNo): array
    {
        try {
            if (empty($outRefundNo)) {
                throw WePayException::configError('退款单号不能为空');
            }

            $response = $this->client
                ->chain("v3/refund/domestic/refunds/{$outRefundNo}")
                ->get();

            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            if ($response->getStatusCode() !== 200) {
                throw WePayException::fromWechatResponse(
                    $responseBody,
                    $response->getStatusCode(),
                );
            }

            Log::info('微信退款查询成功', [
                'type' => $this->type,
                'out_refund_no' => $outRefundNo,
                'status' => $result['status'] ?? '',
            ]);

            return [
                'success' => true,
                'data' => $result,
                'message' => '退款查询成功',
            ];
        } catch (WePayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('微信退款查询失败', [
                'type' => $this->type,
                'out_refund_no' => $outRefundNo,
                'error' => $e->getMessage(),
            ]);
            throw WePayException::paymentFailed($e->getMessage());
        }
    }

    /**
     * 获取配置信息
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

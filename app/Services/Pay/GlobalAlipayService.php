<?php

declare(strict_types=1);

namespace App\Services\Pay;

use Alipay\EasySDK\Kernel\Base;
use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\EasySDKKernel;
use Alipay\EasySDK\Kernel\Payment;
use Alipay\EasySDK\Kernel\Util;
use App\Exceptions\AlipayException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class GlobalAlipayService
 *
 * 全球支付宝服务类，基于 EasySDK 封装跨境支付功能。
 * 每个实例持有独立 SDK 内核，避免全局静态 Factory 的配置冲突。
 */
class GlobalAlipayService extends AbstractAlipayService
{
    /**
     * 支持的支付类型
     */
    public const PAYMENT_TYPES = [
        'web' => '跨境电脑网站支付',
        'app' => '跨境APP支付',
        'wap' => '跨境手机网站支付',
        'qrcode' => '跨境当面付（预创建扫码）',
    ];

    /**
     * 支持的币种
     */
    public const SUPPORTED_CURRENCIES = [
        'USD' => '美元',
        'EUR' => '欧元',
        'GBP' => '英镑',
        'JPY' => '日元',
        'KRW' => '韩元',
        'HKD' => '港币',
        'SGD' => '新加坡元',
        'AUD' => '澳元',
        'CAD' => '加元',
        'CHF' => '瑞士法郎',
    ];

    /**
     * 交易状态描述
     */
    public const TRADE_STATUS = [
        'WAIT_BUYER_PAY' => '交易创建，等待买家付款',
        'TRADE_CLOSED' => '未付款交易超时关闭，或支付完成后全额退款',
        'TRADE_SUCCESS' => '交易支付成功',
        'TRADE_FINISHED' => '交易结束，不可退款',
    ];

    // -------------------------------------------------------------------------
    // 静态工厂方法
    // -------------------------------------------------------------------------

    /** 跨境电脑网站支付 */
    public static function web(array $config = []): static
    {
        return new static('web', $config);
    }

    /** 跨境 APP 支付 */
    public static function app(array $config = []): static
    {
        return new static('app', $config);
    }

    /** 跨境手机网站支付 */
    public static function wap(array $config = []): static
    {
        return new static('wap', $config);
    }

    /** 跨境当面付（预创建二维码） */
    public static function qrcode(array $config = []): static
    {
        return new static('qrcode', $config);
    }

    // -------------------------------------------------------------------------
    // 模板方法实现
    // -------------------------------------------------------------------------

    /**
     * 合并默认配置
     */
    protected function mergeDefaultConfig(array $config): array
    {
        $defaults = [
            'protocol' => 'https',
            'gateway_host' => 'openapi.alipay.com',
            'sign_type' => 'RSA2',
            'currency' => 'USD',
            'language' => 'en',
            'country_code' => 'US',
            'sandbox' => false,
        ];

        return array_merge($defaults, config('pay.alipay_global', []), $config);
    }

    /**
     * 验证支付类型
     *
     * @throws AlipayException
     */
    protected function validateType(string $type): void
    {
        if (! array_key_exists($type, self::PAYMENT_TYPES)) {
            throw AlipayException::validationError(
                "不支持的全球支付类型: {$type}，支持: ".implode(', ', array_keys(self::PAYMENT_TYPES)),
            );
        }
    }

    /**
     * 验证配置完整性
     *
     * @throws AlipayException
     */
    protected function validateConfig(array $config): void
    {
        $required = [
            'app_id' => '应用ID',
            'private_key' => '应用私钥',
            'alipay_public_key' => '支付宝公钥',
        ];

        $missing = [];
        foreach ($required as $key => $name) {
            if (empty($config[$key])) {
                $missing[] = "{$name}({$key})";
            }
        }

        if (! empty($missing)) {
            throw AlipayException::configError('全球支付宝配置缺失: '.implode(', ', $missing));
        }

        if (! empty($config['currency']) && ! isset(self::SUPPORTED_CURRENCIES[$config['currency']])) {
            throw AlipayException::configError(
                "不支持的币种: {$config['currency']}，支持: ".implode(', ', array_keys(self::SUPPORTED_CURRENCIES)),
            );
        }

        $this->validateKeyFormat($config['private_key'], '应用私钥');
        $this->validateKeyFormat($config['alipay_public_key'], '支付宝公钥');
    }

    /**
     * 初始化 SDK 内核，创建本地客户端实例
     *
     * @throws AlipayException
     */
    protected function initConfig(): void
    {
        try {
            $options = new Config();
            $options->protocol = (string) $this->config['protocol'];
            $options->gatewayHost = $this->isSandbox() ? 'openapi.alipaydev.com' : (string) $this->config['gateway_host'];
            $options->signType = (string) $this->config['sign_type'];
            $options->appId = (string) $this->config['app_id'];
            $options->merchantPrivateKey = (string) $this->config['private_key'];
            $options->alipayPublicKey = (string) $this->config['alipay_public_key'];
            $options->notifyUrl = (string) ($this->config['notify_url'] ?? '');
            $options->encryptKey = (string) ($this->config['encrypt_key'] ?? '');

            // 证书模式（可选）
            if (! empty($this->config['app_cert_path'])) {
                $options->merchantCertPath = $this->config['app_cert_path'];
                $options->alipayCertPath = $this->config['alipay_cert_path'];
                $options->alipayRootCertPath = $this->config['root_cert_path'];
            }

            $kernel = new EasySDKKernel($options);
            $this->payment = new Payment($kernel);
            $this->base = new Base($kernel);
            $this->util = new Util($kernel);

            Log::debug('全球支付宝SDK实例化完成', [
                'app_id' => $options->appId,
                'currency' => $this->config['currency'],
                'sandbox' => $this->isSandbox(),
            ]);
        } catch (Throwable $e) {
            throw AlipayException::configError("全球支付宝SDK初始化失败: {$e->getMessage()}");
        }
    }

    // -------------------------------------------------------------------------
    // 支付接口
    // -------------------------------------------------------------------------

    /**
     * 统一支付入口
     *
     * @throws AlipayException
     */
    public function pay(string $outTradeNo, float|int $totalAmount, string $subject, array $options = []): array
    {
        $this->validateOrderParams($outTradeNo, (float) $totalAmount, $subject);

        $amount = $this->formatAmount((float) $totalAmount);

        Log::info('发起全球支付宝支付', [
            'type' => $this->type,
            'out_trade_no' => $outTradeNo,
            'amount' => $amount,
            'currency' => $this->config['currency'],
            'subject' => $subject,
        ]);

        try {
            $result = match ($this->type) {
                'web' => $this->handleWebPay($subject, $outTradeNo, $amount, $options),
                'app' => $this->handleAppPay($subject, $outTradeNo, $amount),
                'wap' => $this->handleWapPay($subject, $outTradeNo, $amount, $options),
                'qrcode' => $this->handleQrCodePay($subject, $outTradeNo, $amount),
                default => throw AlipayException::validationError("不支持的支付类型: {$this->type}"),
            };

            Log::info('全球支付宝支付创建成功', [
                'type' => $this->type,
                'out_trade_no' => $outTradeNo,
                'currency' => $this->config['currency'],
            ]);

            return $result;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('全球支付宝支付失败', [
                'type' => $this->type,
                'out_trade_no' => $outTradeNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("全球支付创建失败: {$e->getMessage()}");
        }
    }

    /**
     * 处理跨境电脑网站支付（对应 EasySDK payment().page()）
     */
    protected function handleWebPay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        $returnUrl = $options['return_url'] ?? $this->config['return_url'] ?? '';

        $result = $this->payment->page()
            ->batchOptional($options['optional'] ?? [])
            ->pay($subject, $outTradeNo, $amount, $returnUrl);

        return [
            'success' => true,
            'type' => 'web',
            'payment_url' => $result->body,
            'out_trade_no' => $outTradeNo,
            'currency' => $this->config['currency'],
        ];
    }

    /**
     * 处理跨境 APP 支付（对应 EasySDK payment().app()）
     */
    protected function handleAppPay(string $subject, string $outTradeNo, string $amount): array
    {
        $result = $this->payment->app()->pay($subject, $outTradeNo, $amount);

        return [
            'success' => true,
            'type' => 'app',
            'order_string' => $result->body,
            'out_trade_no' => $outTradeNo,
            'currency' => $this->config['currency'],
        ];
    }

    /**
     * 处理跨境手机网站支付（对应 EasySDK payment().wap()）
     */
    protected function handleWapPay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        $quitUrl = $options['quit_url'] ?? '';
        $returnUrl = $options['return_url'] ?? $this->config['return_url'] ?? '';

        $result = $this->payment->wap()
            ->batchOptional($options['optional'] ?? [])
            ->pay($subject, $outTradeNo, $amount, $quitUrl, $returnUrl);

        return [
            'success' => true,
            'type' => 'wap',
            'payment_url' => $result->body,
            'out_trade_no' => $outTradeNo,
            'currency' => $this->config['currency'],
        ];
    }

    /**
     * 处理跨境当面付预创建（对应 EasySDK payment().faceToFace().preCreate()）
     *
     * @throws AlipayException
     */
    protected function handleQrCodePay(string $subject, string $outTradeNo, string $amount): array
    {
        $result = $this->payment->faceToFace()->preCreate($subject, $outTradeNo, $amount);

        if ($result->code !== '10000') {
            throw AlipayException::paymentFailed(
                '跨境扫码支付失败: '.($result->subMsg ?? $result->msg ?? '未知错误'),
            );
        }

        return [
            'success' => true,
            'type' => 'qrcode',
            'qr_code' => $result->qrCode,
            'out_trade_no' => $outTradeNo,
            'currency' => $this->config['currency'],
            'trade_no' => $result->tradeNo ?? '',
        ];
    }

    // -------------------------------------------------------------------------
    // 订单与退款
    // -------------------------------------------------------------------------

    /**
     * 查询订单状态
     *
     * @throws AlipayException
     */
    public function queryOrder(string $outTradeNo): array
    {
        if (empty($outTradeNo)) {
            throw AlipayException::validationError('商户订单号不能为空');
        }

        try {
            $result = $this->payment->common()->query($outTradeNo);

            $queryResult = [
                'success' => $result->code === '10000',
                'trade_status' => $result->tradeStatus ?? '',
                'trade_no' => $result->tradeNo ?? '',
                'out_trade_no' => $outTradeNo,
                'total_amount' => $result->totalAmount ?? '',
                'currency' => $this->config['currency'],
                'buyer_pay_amount' => $result->buyerPayAmount ?? '',
                'is_paid' => in_array($result->tradeStatus ?? '', ['TRADE_SUCCESS', 'TRADE_FINISHED']),
                'message' => $result->msg ?? '',
                'response' => $result,
            ];

            Log::info('全球支付宝订单查询', [
                'out_trade_no' => $outTradeNo,
                'trade_status' => $queryResult['trade_status'],
                'is_paid' => $queryResult['is_paid'],
            ]);

            return $queryResult;
        } catch (Throwable $e) {
            Log::error('全球支付宝订单查询失败', [
                'out_trade_no' => $outTradeNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("订单查询失败: {$e->getMessage()}");
        }
    }

    /**
     * 申请退款
     *
     * @throws AlipayException
     */
    public function refund(string $outTradeNo, float|int $refundAmount, ?string $outRequestNo = null, string $refundReason = '用户申请退款'): array
    {
        if (empty($outTradeNo)) {
            throw AlipayException::validationError('商户订单号不能为空');
        }

        if ($refundAmount <= 0) {
            throw AlipayException::validationError('退款金额必须大于0');
        }

        $formattedAmount = $this->formatAmount($refundAmount);
        $refundNo = $outRequestNo ?: 'refund_'.$outTradeNo.'_'.time();

        Log::info('发起全球支付宝退款', [
            'out_trade_no' => $outTradeNo,
            'refund_amount' => $formattedAmount,
            'currency' => $this->config['currency'],
        ]);

        try {
            // EasySDK common().refund() 签名: refund($outTradeNo, $refundAmount, $outRequestNo)
            $result = $this->payment->common()->refund($outTradeNo, $formattedAmount, $refundNo);

            $refundResult = [
                'success' => $result->code === '10000',
                'trade_no' => $result->tradeNo ?? '',
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $refundNo,
                'refund_amount' => $result->refundFee ?? $formattedAmount,
                'currency' => $this->config['currency'],
                'buyer_pay_amount' => $result->buyerPayAmount ?? '',
                'message' => $result->msg ?? '',
                'response' => $result,
            ];

            Log::info('全球支付宝退款结果', [
                'out_trade_no' => $outTradeNo,
                'success' => $refundResult['success'],
                'refund_amount' => $formattedAmount,
            ]);

            return $refundResult;
        } catch (Throwable $e) {
            Log::error('全球支付宝退款失败', [
                'out_trade_no' => $outTradeNo,
                'refund_amount' => $formattedAmount,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("退款失败: {$e->getMessage()}");
        }
    }

    /**
     * 查询退款状态
     *
     * @throws AlipayException
     */
    public function queryRefund(string $outTradeNo, string $outRequestNo): array
    {
        if (empty($outTradeNo)) {
            throw AlipayException::validationError('商户订单号不能为空');
        }

        if (empty($outRequestNo)) {
            throw AlipayException::validationError('退款请求号不能为空');
        }

        try {
            $result = $this->payment->common()->queryRefund($outTradeNo, $outRequestNo);

            $queryResult = [
                'success' => $result->code === '10000',
                'trade_no' => $result->tradeNo ?? '',
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $outRequestNo,
                'refund_amount' => $result->refundAmount ?? '',
                'refund_status' => $result->refundStatus ?? '',
                'currency' => $this->config['currency'],
                'message' => $result->msg ?? '',
                'response' => $result,
            ];

            Log::info('全球支付宝退款查询', [
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $outRequestNo,
                'refund_status' => $queryResult['refund_status'],
            ]);

            return $queryResult;
        } catch (Throwable $e) {
            Log::error('全球支付宝退款查询失败', [
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $outRequestNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("退款查询失败: {$e->getMessage()}");
        }
    }

    /**
     * 关闭订单
     *
     * @throws AlipayException
     */
    public function closeOrder(string $outTradeNo): array
    {
        if (empty($outTradeNo)) {
            throw AlipayException::validationError('商户订单号不能为空');
        }

        try {
            $result = $this->payment->common()->close($outTradeNo);

            $closeResult = [
                'success' => $result->code === '10000',
                'trade_no' => $result->tradeNo ?? '',
                'out_trade_no' => $outTradeNo,
                'message' => $result->msg ?? '',
                'response' => $result,
            ];

            Log::info('全球支付宝订单关闭', [
                'out_trade_no' => $outTradeNo,
                'success' => $closeResult['success'],
            ]);

            return $closeResult;
        } catch (Throwable $e) {
            Log::error('全球支付宝订单关闭失败', [
                'out_trade_no' => $outTradeNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("关闭订单失败: {$e->getMessage()}");
        }
    }

    // -------------------------------------------------------------------------
    // 回调处理
    // -------------------------------------------------------------------------

    /**
     * 处理支付异步通知
     *
     * @throws AlipayException
     */
    public function handleCallback(array $params): array
    {
        Log::info('收到全球支付宝异步通知', ['out_trade_no' => $params['out_trade_no'] ?? '']);

        try {
            if (! $this->verifyNotify($params)) {
                throw AlipayException::signatureError('全球支付回调签名验证失败');
            }

            $tradeStatus = $params['trade_status'] ?? '';
            $isPaid = in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED']);

            $result = [
                'success' => true,
                'is_paid' => $isPaid,
                'trade_status' => $tradeStatus,
                'trade_no' => $params['trade_no'] ?? '',
                'out_trade_no' => $params['out_trade_no'] ?? '',
                'total_amount' => $params['total_amount'] ?? 0,
                'currency' => $this->config['currency'],
                'buyer_pay_amount' => $params['buyer_pay_amount'] ?? '',
                'params' => $params,
            ];

            Log::info('全球支付宝通知处理完成', [
                'out_trade_no' => $result['out_trade_no'],
                'is_paid' => $isPaid,
            ]);

            return $result;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('全球支付宝通知处理失败', ['error' => $e->getMessage()]);

            throw AlipayException::paymentFailed("通知处理失败: {$e->getMessage()}");
        }
    }

    /**
     * 验证异步通知签名
     */
    public function verifyNotify(array $params): bool
    {
        try {
            return $this->payment->common()->verifyNotify($params);
        } catch (Throwable $e) {
            Log::warning('全球支付宝签名验证异常', ['error' => $e->getMessage()]);

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // 辅助方法
    // -------------------------------------------------------------------------

    /**
     * 获取支付类型描述
     */
    public function getTypeDescription(): string
    {
        return self::PAYMENT_TYPES[$this->type] ?? '未知类型';
    }

    /**
     * 获取当前币种
     */
    public function getCurrency(): string
    {
        return $this->config['currency'];
    }

    /**
     * 设置币种（链式调用）
     *
     * @throws AlipayException
     */
    public function setCurrency(string $currency): static
    {
        if (! isset(self::SUPPORTED_CURRENCIES[$currency])) {
            throw AlipayException::validationError("不支持的币种: {$currency}");
        }

        $this->config['currency'] = $currency;

        return $this;
    }

    /**
     * 设置国家代码（链式调用）
     */
    public function setCountryCode(string $countryCode): static
    {
        $this->config['country_code'] = $countryCode;

        return $this;
    }
}

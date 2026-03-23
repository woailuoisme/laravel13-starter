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
 * Class AlipayService
 *
 * 国内支付宝支付服务类，基于 EasySDK 封装。
 * 每个实例持有独立的 SDK 内核，避免全局静态 Factory 的冲突。
 */
class AlipayService extends AbstractAlipayService
{
    /**
     * 支持的支付类型
     */
    public const PAYMENT_TYPES = [
        'page' => '电脑网站支付',
        'app' => 'APP支付',
        'wap' => '手机网站支付',
        'face_to_face' => '当面付',
    ];

    /**
     * 交易状态常量
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

    /** 创建电脑网站支付实例 */
    public static function page(array $config = []): static
    {
        return new static('page', $config);
    }

    /** 创建 APP 支付实例 */
    public static function app(array $config = []): static
    {
        return new static('app', $config);
    }

    /** 创建手机网站支付实例 */
    public static function wap(array $config = []): static
    {
        return new static('wap', $config);
    }

    /** 创建当面付实例 */
    public static function faceToFace(array $config = []): static
    {
        return new static('face_to_face', $config);
    }

    // -------------------------------------------------------------------------
    // 模板方法实现
    // -------------------------------------------------------------------------

    /**
     * 合并默认配置
     */
    protected function mergeDefaultConfig(array $config): array
    {
        return array_merge(config('pay.alipay', []), $config);
    }

    /**
     * 验证支付类型
     *
     * @throws AlipayException
     */
    protected function validateType(string $type): void
    {
        if (! isset(self::PAYMENT_TYPES[$type])) {
            throw AlipayException::configError(
                "不支持的支付宝支付类型: {$type}，支持的类型: ".implode(', ', array_keys(self::PAYMENT_TYPES)),
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
            'public_key' => '支付宝公钥',
        ];

        $missing = [];
        foreach ($required as $key => $name) {
            if (empty($config[$key])) {
                $missing[] = "{$name}({$key})";
            }
        }

        if (! empty($missing)) {
            throw AlipayException::configError('支付宝配置缺失: '.implode(', ', $missing));
        }

        $this->validateKeyFormat($config['private_key'], '应用私钥');
        $this->validateKeyFormat($config['public_key'], '支付宝公钥');
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
            $options->protocol = 'https';
            $options->gatewayHost = $this->isSandbox() ? 'openapi.alipaydev.com' : 'openapi.alipay.com';
            $options->signType = 'RSA2';
            $options->appId = (string) $this->config['app_id'];
            $options->merchantPrivateKey = (string) $this->config['private_key'];
            $options->alipayPublicKey = (string) $this->config['public_key'];
            $options->notifyUrl = (string) ($this->config['notify_url'] ?? '');

            // 可选：证书模式
            if (! empty($this->config['app_cert_path'])) {
                $options->merchantCertPath = $this->config['app_cert_path'];
                $options->alipayCertPath = $this->config['alipay_cert_path'];
                $options->alipayRootCertPath = $this->config['root_cert_path'];
            }

            $kernel = new EasySDKKernel($options);
            $this->payment = new Payment($kernel);
            $this->base = new Base($kernel);
            $this->util = new Util($kernel);

            Log::debug('支付宝SDK实例化完成', [
                'app_id' => $options->appId,
                'sandbox' => $this->isSandbox(),
            ]);
        } catch (Throwable $e) {
            throw AlipayException::configError("支付宝SDK初始化失败: {$e->getMessage()}");
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
        $this->validateOrderParams($outTradeNo, $totalAmount, $subject);

        $amount = $this->formatAmount($totalAmount);

        Log::info('发起支付宝支付', [
            'type' => $this->type,
            'out_trade_no' => $outTradeNo,
            'amount' => $amount,
            'subject' => $subject,
        ]);

        try {
            $result = match ($this->type) {
                'page' => $this->handlePagePay($subject, $outTradeNo, $amount, $options),
                'app' => $this->handleAppPay($subject, $outTradeNo, $amount),
                'wap' => $this->handleWapPay($subject, $outTradeNo, $amount, $options),
                'face_to_face' => $this->handleFaceToFacePay($subject, $outTradeNo, $amount, $options),
                default => throw AlipayException::configError("不支持的支付类型: {$this->type}"),
            };

            Log::info('支付宝支付创建成功', [
                'type' => $this->type,
                'out_trade_no' => $outTradeNo,
            ]);

            return $result;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('支付宝支付失败', [
                'type' => $this->type,
                'out_trade_no' => $outTradeNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed($e->getMessage(), [
                'out_trade_no' => $outTradeNo,
                'amount' => $amount,
                'subject' => $subject,
            ]);
        }
    }

    /**
     * 处理电脑网站支付
     */
    protected function handlePagePay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        $returnUrl = $options['return_url'] ?? $this->config['return_url'] ?? '';

        $result = $this->payment->page()
            ->batchOptional($options['optional'] ?? [])
            ->pay($subject, $outTradeNo, $amount, $returnUrl);

        return [
            'success' => true,
            'type' => 'page',
            'payment_url' => $result->body,
            'out_trade_no' => $outTradeNo,
        ];
    }

    /**
     * 处理 APP 支付
     */
    protected function handleAppPay(string $subject, string $outTradeNo, string $amount): array
    {
        $result = $this->payment->app()->pay($subject, $outTradeNo, $amount);

        return [
            'success' => true,
            'type' => 'app',
            'order_string' => $result->body,
            'out_trade_no' => $outTradeNo,
        ];
    }

    /**
     * 处理手机网站支付
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
        ];
    }

    /**
     * 处理当面付（被扫）
     *
     * @throws AlipayException
     */
    protected function handleFaceToFacePay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        if (empty($options['auth_code'])) {
            throw AlipayException::validationError('当面付需要提供授权码(auth_code)');
        }

        $result = $this->payment->faceToFace()
            ->batchOptional($options['optional'] ?? [])
            ->pay($subject, $outTradeNo, $amount, $options['auth_code']);

        if ($result->code !== '10000') {
            throw AlipayException::fromAlipayResponse($result, '当面付支付失败');
        }

        return [
            'success' => true,
            'type' => 'face_to_face',
            'trade_no' => $result->tradeNo,
            'out_trade_no' => $outTradeNo,
            'buyer_pay_amount' => $result->buyerPayAmount,
            'response' => $result,
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

            if ($result->code !== '10000') {
                throw AlipayException::fromAlipayResponse($result, '订单查询失败');
            }

            $queryResult = [
                'success' => true,
                'trade_status' => $result->tradeStatus,
                'trade_no' => $result->tradeNo,
                'out_trade_no' => $outTradeNo,
                'total_amount' => $result->totalAmount,
                'buyer_pay_amount' => $result->buyerPayAmount,
                'is_paid' => in_array($result->tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED']),
                'response' => $result,
            ];

            Log::info('支付宝订单查询', [
                'out_trade_no' => $outTradeNo,
                'trade_status' => $queryResult['trade_status'],
                'is_paid' => $queryResult['is_paid'],
            ]);

            return $queryResult;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('支付宝订单查询失败', [
                'out_trade_no' => $outTradeNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("订单查询失败: {$e->getMessage()}", [
                'out_trade_no' => $outTradeNo,
            ]);
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

        Log::info('发起支付宝退款', [
            'out_trade_no' => $outTradeNo,
            'refund_amount' => $formattedAmount,
            'refund_no' => $refundNo,
            'reason' => $refundReason,
        ]);

        try {
            $result = $this->payment->common()->refund($outTradeNo, $formattedAmount, $refundNo);

            if ($result->code !== '10000') {
                throw AlipayException::fromAlipayResponse($result, '退款申请失败');
            }

            $refundResult = [
                'success' => true,
                'trade_no' => $result->tradeNo,
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $refundNo,
                'refund_amount' => $formattedAmount,
                'buyer_pay_amount' => $result->buyerPayAmount ?? '',
                'response' => $result,
            ];

            Log::info('支付宝退款成功', [
                'out_trade_no' => $outTradeNo,
                'refund_amount' => $formattedAmount,
            ]);

            return $refundResult;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('支付宝退款失败', [
                'out_trade_no' => $outTradeNo,
                'refund_amount' => $formattedAmount,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("退款失败: {$e->getMessage()}", [
                'out_trade_no' => $outTradeNo,
                'refund_amount' => $formattedAmount,
            ]);
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

            if ($result->code !== '10000') {
                throw AlipayException::fromAlipayResponse($result, '退款查询失败');
            }

            $queryResult = [
                'success' => true,
                'trade_no' => $result->tradeNo ?? '',
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $outRequestNo,
                'refund_amount' => $result->refundAmount ?? '',
                'refund_status' => $result->refundStatus ?? '',
                'response' => $result,
            ];

            Log::info('支付宝退款查询', [
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $outRequestNo,
                'refund_status' => $queryResult['refund_status'],
            ]);

            return $queryResult;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('支付宝退款查询失败', [
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $outRequestNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("退款查询失败: {$e->getMessage()}", [
                'out_trade_no' => $outTradeNo,
                'out_request_no' => $outRequestNo,
            ]);
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

            if ($result->code !== '10000') {
                throw AlipayException::fromAlipayResponse($result, '订单关闭失败');
            }

            $closeResult = [
                'success' => true,
                'out_trade_no' => $outTradeNo,
                'trade_no' => $result->tradeNo ?? '',
                'response' => $result,
            ];

            Log::info('支付宝订单已关闭', ['out_trade_no' => $outTradeNo]);

            return $closeResult;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('支付宝订单关闭失败', [
                'out_trade_no' => $outTradeNo,
                'error' => $e->getMessage(),
            ]);

            throw AlipayException::paymentFailed("订单关闭失败: {$e->getMessage()}", [
                'out_trade_no' => $outTradeNo,
            ]);
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
        Log::info('收到支付宝异步通知', ['out_trade_no' => $params['out_trade_no'] ?? '']);

        try {
            if (! $this->verifyNotify($params)) {
                throw AlipayException::signatureError('回调签名验证失败');
            }

            $tradeStatus = $params['trade_status'] ?? '';

            $result = [
                'success' => true,
                'trade_status' => $tradeStatus,
                'out_trade_no' => $params['out_trade_no'] ?? '',
                'trade_no' => $params['trade_no'] ?? '',
                'total_amount' => $params['total_amount'] ?? '',
                'is_paid' => in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED']),
                'params' => $params,
            ];

            Log::info('支付宝通知处理完成', [
                'out_trade_no' => $result['out_trade_no'],
                'is_paid' => $result['is_paid'],
            ]);

            return $result;
        } catch (AlipayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('支付宝通知处理失败', ['error' => $e->getMessage()]);

            throw AlipayException::signatureError("通知处理失败: {$e->getMessage()}");
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
            Log::warning('支付宝签名验证异常', ['error' => $e->getMessage()]);

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
}

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
use Illuminate\Support\Str;
use Throwable;

/**
 * Class AlipayService
 *
 * 国内支付宝支付服务类，基于 EasySDK 封装。
 * 每个实例持有独立的 SDK 内核，避免全局静态 Factory 的冲突。
 */
class AlipayService extends AbstractAlipayService
{
    /** 支持的支付类型 */
    public const array PAYMENT_TYPES = [
        'page' => '电脑网站支付',
        'app' => 'APP支付',
        'wap' => '手机网站支付',
        'face_to_face' => '当面付',
    ];

    // --- 静态工厂方法 ---

    public static function page(array $config = []): self
    {
        return new self('page', $config);
    }

    public static function app(array $config = []): self
    {
        return new self('app', $config);
    }

    public static function wap(array $config = []): self
    {
        return new self('wap', $config);
    }

    public static function faceToFace(array $config = []): self
    {
        return new self('face_to_face', $config);
    }

    // --- 内部逻辑实现 ---

    protected function mergeDefaultConfig(array $config): array
    {
        return array_merge(config('pay.alipay', []), $config);
    }

    protected function validateType(string $type): void
    {
        if (! isset(self::PAYMENT_TYPES[$type])) {
            throw AlipayException::configError("不支持的支付类型: {$type}");
        }
    }

    protected function validateConfig(array $config): void
    {
        foreach (['app_id', 'private_key', 'public_key'] as $key) {
            if (empty($config[$key])) {
                throw AlipayException::configError("支付宝配置缺失: {$key}");
            }
        }
        $this->validateKeyFormat((string) $config['private_key'], '私钥');
        $this->validateKeyFormat((string) $config['public_key'], '公钥');
    }

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

            if (! empty($this->config['app_cert_path'])) {
                $options->merchantCertPath = $this->config['app_cert_path'];
                $options->alipayCertPath = $this->config['alipay_cert_path'];
                $options->alipayRootCertPath = $this->config['root_cert_path'];
            }

            $kernel = new EasySDKKernel($options);
            $this->payment = new Payment($kernel);
            $this->base = new Base($kernel);
            $this->util = new Util($kernel);
        } catch (Throwable $e) {
            throw AlipayException::configError("SDK 初始化失败: {$e->getMessage()}");
        }
    }

    // --- 业务接口 ---

    public function pay(string $outTradeNo, float|int $totalAmount, string $subject, array $options = []): array
    {
        $this->validateOrderParams($outTradeNo, $totalAmount, $subject);
        $amount = $this->formatAmount($totalAmount);

        try {
            Log::info("支付宝支付下单: [{$this->type}] {$outTradeNo}", ['amount' => $amount]);

            return match ($this->type) {
                'page' => $this->handlePagePay($subject, $outTradeNo, $amount, $options),
                'app' => $this->handleAppPay($subject, $outTradeNo, $amount),
                'wap' => $this->handleWapPay($subject, $outTradeNo, $amount, $options),
                'face_to_face' => $this->handleFaceToFacePay($subject, $outTradeNo, $amount, $options),
                default => throw AlipayException::configError("暂不支持的支付类型: {$this->type}"),
            };
        } catch (Throwable $e) {
            Log::error("支付宝支付下单失败: {$outTradeNo}", ['error' => $e->getMessage()]);
            throw ($e instanceof AlipayException) ? $e : AlipayException::paymentFailed($e->getMessage());
        }
    }

    public function queryOrder(string $outTradeNo): array
    {
        try {
            $result = $this->payment->common()->query($outTradeNo);
            if ($result->code !== '10000') {
                throw AlipayException::fromAlipayResponse($result, '查询失败');
            }

            return [
                'success' => true,
                'trade_status' => $result->tradeStatus,
                'trade_no' => $result->tradeNo,
                'is_paid' => in_array($result->tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED']),
                'raw' => $result,
            ];
        } catch (Throwable $e) {
            throw ($e instanceof AlipayException) ? $e : AlipayException::paymentFailed($e->getMessage());
        }
    }

    public function refund(string $outTradeNo, float|int $refundAmount, ?string $outRequestNo = null, string $reason = '商户退款'): array
    {
        try {
            $amount = $this->formatAmount($refundAmount);
            $refundNo = $outRequestNo ?: 'REF'.time().Str::random(6);

            $result = $this->payment->common()->refund($outTradeNo, $amount, $refundNo);
            if ($result->code !== '10000') {
                throw AlipayException::fromAlipayResponse($result, '退款失败');
            }

            return ['success' => true, 'trade_no' => $result->tradeNo, 'refund_no' => $refundNo];
        } catch (Throwable $e) {
            throw ($e instanceof AlipayException) ? $e : AlipayException::paymentFailed($e->getMessage());
        }
    }

    public function getTypeDescription(): string
    {
        return self::PAYMENT_TYPES[$this->type] ?? '未知类型';
    }

    // --- 辅助处理方法 ---

    protected function handlePagePay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        $returnUrl = $options['return_url'] ?? $this->config['return_url'] ?? '';
        $result = $this->payment->page()->batchOptional($options['optional'] ?? [])->pay($subject, $outTradeNo, $amount, $returnUrl);

        return ['success' => true, 'type' => 'page', 'form' => $result->body];
    }

    protected function handleAppPay(string $subject, string $outTradeNo, string $amount): array
    {
        $result = $this->payment->app()->pay($subject, $outTradeNo, $amount);

        return ['success' => true, 'type' => 'app', 'order_string' => $result->body];
    }

    protected function handleWapPay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        $quitUrl = $options['quit_url'] ?? '';
        $returnUrl = $options['return_url'] ?? $this->config['return_url'] ?? '';
        $result = $this->payment->wap()->batchOptional($options['optional'] ?? [])->pay($subject, $outTradeNo, $amount, $quitUrl, $returnUrl);

        return ['success' => true, 'type' => 'wap', 'form' => $result->body];
    }

    protected function handleFaceToFacePay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        if (empty($options['auth_code'])) {
            throw AlipayException::validationError('当面付缺少 auth_code');
        }
        $result = $this->payment->faceToFace()->batchOptional($options['optional'] ?? [])->pay($subject, $outTradeNo, $amount, $options['auth_code']);

        if ($result->code !== '10000') {
            throw AlipayException::fromAlipayResponse($result, '当面付提交失败');
        }

        return ['success' => true, 'type' => 'face_to_face', 'trade_no' => $result->tradeNo];
    }
}

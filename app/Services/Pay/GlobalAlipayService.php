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
 * Class GlobalAlipayService
 *
 * 全球支付宝服务类，基于 EasySDK 封装跨境支付功能。
 * 每个实例持有独立 SDK 内核，避免全局静态 Factory 的配置冲突。
 */
class GlobalAlipayService extends AbstractAlipayService
{
    /** 支持的支付类型 */
    public const array PAYMENT_TYPES = [
        'web' => '跨境电脑网站支付',
        'app' => '跨境APP支付',
        'wap' => '跨境手机网站支付',
        'qrcode' => '跨境当面付（预创建扫码）',
    ];

    /** 支持的币种 */
    public const array SUPPORTED_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'KRW', 'HKD', 'SGD', 'AUD', 'CAD', 'CHF', 'CNY',
    ];

    // --- 静态工厂方法 ---

    public static function web(array $config = []): self
    {
        return new self('web', $config);
    }

    public static function app(array $config = []): self
    {
        return new self('app', $config);
    }

    public static function wap(array $config = []): self
    {
        return new self('wap', $config);
    }

    public static function qrcode(array $config = []): self
    {
        return new self('qrcode', $config);
    }

    // --- 内部逻辑实现 ---

    protected function mergeDefaultConfig(array $config): array
    {
        $defaults = [
            'currency' => 'USD',
            'sandbox' => false,
        ];

        return array_merge($defaults, config('pay.alipay_global', []), $config);
    }

    protected function validateType(string $type): void
    {
        if (! isset(self::PAYMENT_TYPES[$type])) {
            throw AlipayException::configError("不支持的全球支付类型: {$type}");
        }
    }

    protected function validateConfig(array $config): void
    {
        foreach (['app_id', 'private_key', 'alipay_public_key'] as $key) {
            if (empty($config[$key])) {
                throw AlipayException::configError("全球支付宝配置缺失: {$key}");
            }
        }
        if (! in_array($config['currency'], self::SUPPORTED_CURRENCIES, true)) {
            throw AlipayException::configError("不支持的币种: {$config['currency']}");
        }
    }

    /**
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
            $options->alipayPublicKey = (string) $this->config['alipay_public_key'];
            $options->notifyUrl = (string) ($this->config['notify_url'] ?? '');

            $kernel = new EasySDKKernel($options);
            $this->payment = new Payment($kernel);
            $this->base = new Base($kernel);
            $this->util = new Util($kernel);
        } catch (Throwable $e) {
            throw AlipayException::configError("全球 SDK 初始化失败: {$e->getMessage()}");
        }
    }

    // --- 业务接口 ---

    public function pay(string $outTradeNo, float|int $totalAmount, string $subject, array $options = []): array
    {
        $this->validateOrderParams($outTradeNo, $totalAmount, $subject);
        $amount = $this->formatAmount($totalAmount);

        try {
            Log::info("全球支付宝支付下单: [{$this->type}] {$outTradeNo}", ['amount' => $amount, 'currency' => $this->config['currency']]);

            return match ($this->type) {
                'web' => $this->handleWebPay($subject, $outTradeNo, $amount, $options),
                'app' => $this->handleAppPay($subject, $outTradeNo, $amount),
                'wap' => $this->handleWapPay($subject, $outTradeNo, $amount, $options),
                'qrcode' => $this->handleQrCodePay($subject, $outTradeNo, $amount),
                default => throw AlipayException::configError("暂不支持的跨境类型: {$this->type}"),
            };
        } catch (Throwable $e) {
            Log::error("全球支付宝支付失败: {$outTradeNo}", ['error' => $e->getMessage()]);
            throw ($e instanceof AlipayException) ? $e : AlipayException::paymentFailed($e->getMessage());
        }
    }

    public function queryOrder(string $outTradeNo): array
    {
        try {
            $result = $this->payment->common()->query($outTradeNo);

            return [
                'success' => $result->code === '10000',
                'trade_status' => $result->tradeStatus ?? '',
                'trade_no' => $result->tradeNo ?? '',
                'is_paid' => in_array($result->tradeStatus ?? '', ['TRADE_SUCCESS', 'TRADE_FINISHED']),
                'currency' => $this->config['currency'],
                'raw' => $result,
            ];
        } catch (Throwable $e) {
            throw AlipayException::paymentFailed($e->getMessage());
        }
    }

    public function refund(string $outTradeNo, float|int $refundAmount, ?string $outRequestNo = null): array
    {
        try {
            $amount = $this->formatAmount($refundAmount);
            $refundNo = $outRequestNo ?: 'GREF'.time().Str::random(6);

            $result = $this->payment->common()->refund($outTradeNo, $amount, $refundNo);

            return [
                'success' => $result->code === '10000',
                'trade_no' => $result->tradeNo ?? '',
                'refund_no' => $refundNo,
                'raw' => $result,
            ];
        } catch (Throwable $e) {
            throw AlipayException::paymentFailed($e->getMessage());
        }
    }

    public function getTypeDescription(): string
    {
        return self::PAYMENT_TYPES[$this->type] ?? '未知类型';
    }

    // --- 辅助处理方法 ---

    protected function handleWebPay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        $returnUrl = $options['return_url'] ?? $this->config['return_url'] ?? '';
        $result = $this->payment->page()->batchOptional($options['optional'] ?? [])->pay($subject, $outTradeNo, $amount, $returnUrl);

        return ['success' => true, 'type' => 'web', 'form' => $result->body, 'currency' => $this->config['currency']];
    }

    protected function handleAppPay(string $subject, string $outTradeNo, string $amount): array
    {
        $result = $this->payment->app()->pay($subject, $outTradeNo, $amount);

        return ['success' => true, 'type' => 'app', 'order_string' => $result->body, 'currency' => $this->config['currency']];
    }

    protected function handleWapPay(string $subject, string $outTradeNo, string $amount, array $options): array
    {
        $quitUrl = $options['quit_url'] ?? '';
        $returnUrl = $options['return_url'] ?? $this->config['return_url'] ?? '';
        $result = $this->payment->wap()->batchOptional($options['optional'] ?? [])->pay($subject, $outTradeNo, $amount, $quitUrl, $returnUrl);

        return ['success' => true, 'type' => 'wap', 'form' => $result->body, 'currency' => $this->config['currency']];
    }

    protected function handleQrCodePay(string $subject, string $outTradeNo, string $amount): array
    {
        $result = $this->payment->faceToFace()->preCreate($subject, $outTradeNo, $amount);
        if ($result->code !== '10000') {
            throw AlipayException::fromAlipayResponse($result, '跨境扫码预下单失败');
        }

        return ['success' => true, 'type' => 'qrcode', 'qr_code' => $result->qrCode, 'currency' => $this->config['currency']];
    }
}

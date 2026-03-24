<?php

declare(strict_types=1);

namespace App\Services\Pay;

use Alipay\EasySDK\Kernel\Base;
use Alipay\EasySDK\Kernel\Payment;
use Alipay\EasySDK\Kernel\Util;
use App\Exceptions\AlipayException;
use Throwable;

/**
 * Class AbstractAlipayService
 *
 * 支付宝支付服务基类，提供通用逻辑与 SDK 内核属性。
 * 子类各自持有独立的 EasySDKKernel 实例，避免全局 Factory 状态冲突。
 */
abstract class AbstractAlipayService
{
    /** @var Payment|null EasySDK 支付客户端 */
    protected ?Payment $payment = null;

    /** @var Base|null EasySDK 基础客户端 */
    protected ?Base $base = null;

    /** @var Util|null EasySDK 工具客户端 */
    protected ?Util $util = null;

    /**
     * @param string $type 支付类型
     * @param array $config 支付配置（会与默认配置合并）
     */
    public function __construct(
        protected string $type,
        protected array $config = [],
    ) {
        $this->validateType($this->type);
        $this->config = $this->mergeDefaultConfig($this->config);
        $this->validateConfig($this->config);
        $this->initConfig();
    }

    /**
     * 合并默认配置（子类从 config/pay.php 读取对应项）
     */
    abstract protected function mergeDefaultConfig(array $config): array;

    /**
     * 验证支付类型是否受支持
     */
    abstract protected function validateType(string $type): void;

    /**
     * 验证配置完整性
     */
    abstract protected function validateConfig(array $config): void;

    /**
     * 初始化 EasySDK 内核
     */
    abstract protected function initConfig(): void;

    // -------------------------------------------------------------------------
    // 通用工具方法
    // -------------------------------------------------------------------------

    /**
     * 验证密钥格式
     */
    protected function validateKeyFormat(string $key, string $keyName): void
    {
        $trimKey = mb_trim(str_replace(["\r", "\n", ' '], '', $key));

        if (! str_contains($trimKey, 'BEGIN') && ! str_contains($trimKey, 'END')) {
            if (! preg_match('/^[A-Za-z0-9+\/=]+$/', $trimKey)) {
                throw AlipayException::configError("{$keyName}格式不正确");
            }
        }
    }

    /**
     * 验证订单公共参数
     */
    protected function validateOrderParams(string $outTradeNo, float|int $totalAmount, string $subject): void
    {
        if (empty($outTradeNo)) {
            throw AlipayException::validationError('商户订单号不能为空');
        }

        if (mb_strlen($outTradeNo) > 64) {
            throw AlipayException::validationError('商户订单号长度不能超过64位');
        }

        if (empty($subject)) {
            throw AlipayException::validationError('订单标题不能为空');
        }

        if (mb_strlen($subject) > 256) {
            throw AlipayException::validationError('订单标题长度不能超过256个字符');
        }

        if ($totalAmount <= 0) {
            throw AlipayException::validationError('订单金额必须大于0');
        }

        if ($totalAmount > 100_000_000) {
            throw AlipayException::validationError('订单金额不能超过1亿元');
        }
    }

    /**
     * 获取支付类型描述
     */
    abstract public function getTypeDescription(): string;

    /**
     * 处理支付异步通知
     */
    public function handleCallback(array $params): array
    {
        try {
            if (! $this->verifyNotify($params)) {
                throw AlipayException::signatureError('支付宝签名验证失败');
            }

            $tradeStatus = $params['trade_status'] ?? '';
            $isPaid = in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true);

            return [
                'success' => true,
                'is_paid' => $isPaid,
                'trade_status' => $tradeStatus,
                'out_trade_no' => $params['out_trade_no'] ?? '',
                'trade_no' => $params['trade_no'] ?? '',
                'total_amount' => $params['total_amount'] ?? '',
                'raw' => $params,
            ];
        } catch (Throwable $e) {
            throw ($e instanceof AlipayException) ? $e : AlipayException::signatureError($e->getMessage());
        }
    }

    /**
     * 验证异步通知签名
     */
    public function verifyNotify(array $params): bool
    {
        try {
            return $this->payment->common()->verifyNotify($params);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 格式化金额
     */
    protected function formatAmount(float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /** 获取支付类型 */
    public function getType(): string
    {
        return $this->type;
    }

    /** 获取当前配置 */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** 是否沙箱环境 */
    public function isSandbox(): bool
    {
        return (bool) ($this->config['sandbox'] ?? false);
    }
}

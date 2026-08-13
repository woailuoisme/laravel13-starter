<?php

declare(strict_types=1);

namespace App\Support;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Random\RandomException;
use RuntimeException;

class OrderNumberGenerator
{
    /**
     * 生成带前缀的唯一订单编号
     *
     * @throws RandomException
     */
    public static function generateOrderNo(string $prefix = 'ORD'): string
    {
        return (
            $prefix
            .now()->format('YmdHis')
            .Str::padLeft((string) ((microtime(true) * 10_000) % 10_000), 4, '0')
            .random_int(100_000, 999_999)
        );
    }

    /**
     * 生成商城订单编号（前缀 SO）
     *
     * @throws RandomException
     */
    public static function generateShopOrderNo(): string
    {
        return self::generateOrderNo('SO');
    }

    /**
     * 生成产品订单编号（前缀 PO）
     *
     * @throws RandomException
     */
    public static function generateProductOrderNo(): string
    {
        return self::generateOrderNo('PO');
    }

    /**
     * 生成外部交易流水编号（前缀 OT）
     *
     * @throws RandomException
     */
    public static function generateOutTradeNo(): string
    {
        return self::generateOrderNo('OT');
    }

    /**
     * 生成充值订单编号（前缀 TU）
     *
     * @throws RandomException
     */
    public static function generateTopUpOrderNo(): string
    {
        return self::generateOrderNo('TU');
    }

    /**
     * 生成提现订单编号（前缀 WD）
     */
    public static function generateWithdrawOrderNo(): string
    {
        return self::generateOrderNo('WD');
    }

    /**
     * 生成退款订单编号（前缀 RF）
     *
     * @throws RandomException
     */
    public static function generateRefundOrderNo(): string
    {
        return self::generateOrderNo('RF');
    }

    /**
     * 生成产品退款单号（前缀 PR）
     *
     * @throws RandomException
     */
    public static function generateProductRefoundNo(): string
    {
        return self::generateOrderNo('PR');
    }

    /**
     * 生成基于微秒时间戳的唯一订单号
     */
    public static function orderNumber(): string
    {
        $today = now()->format('YmdHisu');
        $rand = Str::upper(Str::substr(uniqid(sha1($today), true), 0, 4));

        return $today.$rand;
    }

    /**
     * 生成基于时间戳+毫秒+随机数的订单代码
     *
     * @throws RuntimeException 当随机数生成失败时抛出
     */
    public static function orderCode(): string
    {
        try {
            $now = now();
            $milliseconds = Str::padLeft((string) $now->milli, 3, '0');
            $randomNumber = Str::padLeft((string) random_int(1, 999), 3, '0');

            return $now->timestamp.$milliseconds.$randomNumber;
        } catch (Exception $e) {
            Log::error('Failed to generate order code', ['error' => $e->getMessage()]);

            throw new RuntimeException('Unable to generate order code: '.$e->getMessage());
        }
    }
}

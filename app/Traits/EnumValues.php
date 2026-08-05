<?php

declare(strict_types=1);

namespace App\Traits;

use ReflectionEnum;

/**
 * @mixin \UnitEnum
 */
trait EnumValues
{
    /**
     * Get all values of the enum.
     *
     * @return array<int, string|int>
     */
    public static function values(): array
    {
        // 使用反射判断是否为 BackedEnum，避免 PHPStan 在具体枚举上下文报 alreadyNarrowedType 错误
        $reflection = new ReflectionEnum(self::class);
        $cases = call_user_func([self::class, 'cases']);

        return $reflection->isBacked()
            ? array_column($cases, 'value')
            : array_column($cases, 'name');
    }

    /**
     * Get all names of the enum cases.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        $cases = call_user_func([self::class, 'cases']);

        return array_column($cases, 'name');
    }

    /**
     * Get an associative array of name => value.
     *
     * @return array<string, string|int>
     */
    public static function array(): array
    {
        return array_combine(self::names(), self::values());
    }
}

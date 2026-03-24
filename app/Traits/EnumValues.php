<?php

declare(strict_types=1);

namespace App\Traits;

trait EnumValues
{
    /**
     * Get all values of the enum.
     *
     * @return array<int, string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all names of the enum cases.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
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

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class EcommerceSettings extends Settings
{
    public string $site_name = 'My Shop';
    public bool $is_shop_open = true;
    public float $free_shipping_threshold = 0.0;
    public array $allowed_payment_gateways = [];
    public ?string $close_reason = null;

    public static function group(): string
    {
        return 'ecommerce';
    }
}

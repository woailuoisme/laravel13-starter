<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class EcommerceSettings extends Settings
{

    public string $site_name;
    public bool $is_shop_open;
    public float $free_shipping_threshold;
    public array $allowed_payment_gateways;
    public ?string $close_reason;

    public static function group(): string
    {
        return 'ecommerce';
    }
}

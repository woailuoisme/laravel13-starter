<?php

declare(strict_types=1);

use App\Settings\EcommerceSettings;

it('defines defaults for every ecommerce setting property', function (): void {
    $settings = app(EcommerceSettings::class);

    expect($settings->site_name)->toBe('My Shop')
        ->and($settings->is_shop_open)->toBeTrue()
        ->and($settings->free_shipping_threshold)->toBe(0.0)
        ->and($settings->allowed_payment_gateways)->toBe([])
        ->and($settings->close_reason)->toBeNull();
});

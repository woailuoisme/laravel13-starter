<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class () extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('ecommerce.site_name', 'My Shop');
        $this->migrator->add('ecommerce.is_shop_open', true);
        $this->migrator->add('ecommerce.free_shipping_threshold', 0.0);
        $this->migrator->add('ecommerce.allowed_payment_gateways', []);
        $this->migrator->add('ecommerce.close_reason', null);
    }
};

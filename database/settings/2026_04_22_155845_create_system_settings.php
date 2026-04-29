<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class () extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.site_name', 'My Shop');
        $this->migrator->add('system.is_shop_open', true);
        $this->migrator->add('system.free_shipping_threshold', 0.0);
        $this->migrator->add('system.allowed_payment_gateways', []);
        $this->migrator->add('system.close_reason', null);
    }
};

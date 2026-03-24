<?php

namespace App\Filament\Admin\Pages;

use App\Settings\EcommerceSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageEcommerce extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $settings = EcommerceSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_name')
                    ->required(),
                Toggle::make('is_shop_open')
                    ->required(),
                TextInput::make('free_shipping_threshold')
                    ->numeric()
                    ->required(),
                TextInput::make('allowed_payment_gateways')
                    ->required(),
                TextInput::make('close_reason'),
            ]);
    }
}

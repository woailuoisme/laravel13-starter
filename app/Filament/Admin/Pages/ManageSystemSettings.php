<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\FilamentNavigationGroup;
use App\Settings\SystemSettings;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSystemSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected static string|UnitEnum|null $navigationGroup = FilamentNavigationGroup::SystemSettings;
    protected static ?int $navigationSort = -10;

    protected static string $settings = SystemSettings::class;

    public static function getNavigationLabel(): string
    {
        return __('navigation.system_settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_name')
                    ->label(__('admin.pages.manage_ecommerce.fields.site_name'))
                    ->required(),
                Toggle::make('is_shop_open')
                    ->label(__('admin.pages.manage_ecommerce.fields.is_shop_open'))
                    ->required(),
                TextInput::make('free_shipping_threshold')
                    ->label(__('admin.pages.manage_ecommerce.fields.free_shipping_threshold'))
                    ->numeric()
                    ->required(),
                TagsInput::make('allowed_payment_gateways')
                    ->label(__('admin.pages.manage_ecommerce.fields.allowed_payment_gateways'))
                    ->required(),
                TextInput::make('close_reason')
                    ->label(__('admin.pages.manage_ecommerce.fields.close_reason')),
            ]);
    }
}

<?php

declare(strict_types=1);

use App\Enums\FilamentNavigationGroup;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Pages\ManageSystemSettings;
use App\Filament\Admin\Resources\AdminUsers\AdminUserResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Helpers\FilamentConfigurator;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;

it('keeps the dashboard outside the backend management group and pinned to the top', function (): void {
    $property = new ReflectionProperty(Dashboard::class, 'navigationSort');
    $property->setAccessible(true);

    expect(Dashboard::getNavigationGroup())->toBeNull()
        ->and($property->getValue())->toBe(-1000);
});

it('places the admin user, user, ecommerce, and shield role entries in the backend management group', function (): void {
    expect(UserResource::getNavigationGroup())->toBe(FilamentNavigationGroup::BackendManagement)
        ->and(AdminUserResource::getNavigationGroup())->toBe(FilamentNavigationGroup::BackendManagement);

    $adminUserProperty = new ReflectionProperty(AdminUserResource::class, 'navigationSort');
    $adminUserProperty->setAccessible(true);
    $userProperty = new ReflectionProperty(UserResource::class, 'navigationSort');
    $userProperty->setAccessible(true);
    $ecommerceProperty = new ReflectionProperty(ManageSystemSettings::class, 'navigationSort');
    $ecommerceProperty->setAccessible(true);

    $shieldPlugin = collect(FilamentConfigurator::getPlugins())
        ->first(fn (mixed $plugin): bool => $plugin instanceof FilamentShieldPlugin);

    expect($shieldPlugin)->toBeInstanceOf(FilamentShieldPlugin::class)
        ->and($shieldPlugin->getNavigationGroup())->toBe(FilamentNavigationGroup::BackendManagement)
        ->and($adminUserProperty->getValue())->toBe(-30)
        ->and($userProperty->getValue())->toBe(-20);
});

it('labels the ecommerce settings page as system settings', function (): void {
    $property = new ReflectionProperty(ManageSystemSettings::class, 'navigationGroup');
    $property->setAccessible(true);

    expect(ManageSystemSettings::getNavigationLabel())->toBe(__('navigation.system_settings'))
        ->and($property->getValue())->toBe(FilamentNavigationGroup::SystemSettings);
});

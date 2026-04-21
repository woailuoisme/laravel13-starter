<?php

declare(strict_types=1);

use App\Models\AdminUser;

it('points filament shield at the admin user model', function (): void {
    expect(config('filament-shield.auth_provider_model'))->toBe(AdminUser::class);
});

it('keeps shield discovery scoped to the current single panel setup', function (): void {
    expect(config('filament-shield.discovery'))->toBe([
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ]);
});

it('keeps the role based super admin and panel user defaults enabled', function (): void {
    expect(config('filament-shield.super_admin.enabled'))->toBeTrue()
        ->and(config('filament-shield.super_admin.name'))->toBe('super_admin')
        ->and(config('filament-shield.panel_user.enabled'))->toBeTrue()
        ->and(config('filament-shield.panel_user.name'))->toBe('panel_user');
});

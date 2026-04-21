<?php

declare(strict_types=1);

use App\Models\AdminUser;
use Filament\Panel;
use Illuminate\Support\Carbon;

describe('AdminUser model casts', function () {
    it('casts is_active to boolean', function () {
        $admin = AdminUser::factory()->create(['is_active' => true]);
        expect($admin->is_active)->toBeBool()->toBeTrue();
    });

    it('casts last_login_at to a Carbon datetime instance', function () {
        $admin = AdminUser::factory()->create(['last_login_at' => now()]);
        expect($admin->last_login_at)->toBeInstanceOf(Carbon::class);
    });

    it('hashes the password when set', function () {
        $admin = AdminUser::factory()->create(['password' => 'adminpass']);
        expect($admin->password)->not->toBe('adminpass');
        expect(password_verify('adminpass', $admin->password))->toBeTrue();
    });

    it('uses the filament guard by default', function () {
        $admin = AdminUser::factory()->make();
        $property = new ReflectionProperty($admin, 'guard_name');
        $property->setAccessible(true);

        expect($property->getValue($admin))->toBe('filament');
    });
});

describe('AdminUser Filament panel access', function () {
    it('allows panel access when is_active is true', function () {
        $admin = AdminUser::factory()->create(['is_active' => true]);
        $panel = mock(Panel::class);
        expect($admin->canAccessPanel($panel))->toBeTrue();
    });

    it('denies panel access when is_active is false', function () {
        $admin = AdminUser::factory()->create(['is_active' => false]);
        $panel = mock(Panel::class);
        expect($admin->canAccessPanel($panel))->toBeFalse();
    });
});

describe('AdminUser model JWT', function () {
    it('returns the primary key as the JWT identifier', function () {
        $admin = AdminUser::factory()->create();
        expect($admin->getJWTIdentifier())->toBe($admin->getKey());
    });

    it('returns an empty array for JWT custom claims', function () {
        $admin = AdminUser::factory()->make();
        expect($admin->getJWTCustomClaims())->toBeArray()->toBeEmpty();
    });
});

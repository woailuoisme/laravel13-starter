<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\Login;
use App\Models\AdminUser;
use Livewire\Livewire;

it('authenticates admin users with their email address', function (): void {
    $admin = AdminUser::factory()->create([
        'is_active' => true,
    ]);

    Livewire::test(Login::class)
        ->set('data.email', $admin->email)
        ->set('data.password', 'password')
        ->set('data.remember', false)
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(auth('filament')->check())->toBeTrue();
    expect(auth('filament')->user()?->is($admin))->toBeTrue();
});

it('authenticates admin users with a case-insensitive username', function (): void {
    $admin = AdminUser::factory()->create([
        'is_active' => true,
    ]);

    Livewire::test(Login::class)
        ->set('data.email', mb_strtoupper($admin->username))
        ->set('data.password', 'password')
        ->set('data.remember', false)
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(auth('filament')->check())->toBeTrue();
    expect(auth('filament')->user()?->is($admin))->toBeTrue();
});

it('rejects invalid login credentials', function (): void {
    $admin = AdminUser::factory()->create([
        'is_active' => true,
    ]);

    Livewire::test(Login::class)
        ->set('data.email', $admin->email)
        ->set('data.password', 'wrong-password')
        ->set('data.remember', false)
        ->call('authenticate')
        ->assertHasErrors([
            'data.email',
        ]);

    expect(auth('filament')->check())->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Models\AdminUser;
use App\Models\User;
use App\Policies\AdminUserPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

function shieldAdminUser(string $ability, bool $allowed = true, bool $superAdmin = false): AdminUser
{
    $user = mock(AdminUser::class);

    $user->shouldReceive('hasRole')
        ->with(config('filament-shield.super_admin.name', 'super_admin'))
        ->andReturn($superAdmin);

    if (! $superAdmin) {
        $user->shouldReceive('can')
            ->with($ability)
            ->andReturn($allowed);
    }

    return $user;
}

dataset('shield policies', [
    'user' => [UserPolicy::class, 'ViewAny:User'],
    'admin user' => [AdminUserPolicy::class, 'ViewAny:AdminUser'],
    'role' => [RolePolicy::class, 'ViewAny:Role'],
]);

it('authorizes viewAny through shield permissions', function (string $policyClass, string $ability): void {
    $policy = new $policyClass();
    $user = shieldAdminUser($ability);

    expect($policy->viewAny($user))->toBeTrue();
})->with('shield policies');

it('allows super admins regardless of direct permission assignment', function (string $policyClass, string $ability): void {
    $policy = new $policyClass();
    $user = shieldAdminUser($ability, allowed: false, superAdmin: true);

    expect($policy->viewAny($user))->toBeTrue();
})->with('shield policies');

it('denies access when the matching permission is missing', function (string $policyClass, string $ability): void {
    $policy = new $policyClass();
    $user = shieldAdminUser($ability, allowed: false);

    expect($policy->viewAny($user))->toBeFalse();
})->with('shield policies');

it('discovers shield policies automatically', function (): void {
    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class);
    expect(Gate::getPolicyFor(AdminUser::class))->toBeInstanceOf(AdminUserPolicy::class);
    expect(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class);
});

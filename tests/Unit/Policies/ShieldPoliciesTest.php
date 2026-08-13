<?php

declare(strict_types=1);

use App\Models\AdminUser;
use App\Models\User;
use App\Policies\AdminUserPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

function shield_admin_user(string $ability, string $mode = 'allowed'): AdminUser
{
    $superAdmin = $mode === 'superAdmin';
    $allowed = $mode === 'allowed' || $superAdmin;
    $user = mock(AdminUser::class);

    $user
        ->shouldReceive('hasRole')
        ->with(config('filament-shield.super_admin.name', 'super_admin'))
        ->andReturn($superAdmin);

    $user
        ->shouldReceive('can')
        ->with($ability)
        ->andReturn($superAdmin ? true : $allowed);

    return $user;
}

dataset('shield policies', [
    'user' => [UserPolicy::class, 'ViewAny:User'],
    'admin user' => [AdminUserPolicy::class, 'ViewAny:AdminUser'],
    'role' => [RolePolicy::class, 'ViewAny:Role'],
]);

it('authorizes viewAny through shield permissions', function (string $policyClass, string $ability): void {
    $policy = new $policyClass;
    $user = shield_admin_user($ability);

    expect($policy->viewAny($user))->toBeTrue();
})->with('shield policies');

it('allows super admins regardless of direct permission assignment', function (
    string $policyClass,
    string $ability,
): void {
    $policy = new $policyClass;
    $user = shield_admin_user($ability, 'superAdmin');

    expect($policy->viewAny($user))->toBeTrue();
})->with('shield policies');

it('denies access when the matching permission is missing', function (string $policyClass, string $ability): void {
    $policy = new $policyClass;
    $user = shield_admin_user($ability, 'denied');

    expect($policy->viewAny($user))->toBeFalse();
})->with('shield policies');

it('discovers shield policies automatically', function (): void {
    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class);
    expect(Gate::getPolicyFor(AdminUser::class))->toBeInstanceOf(AdminUserPolicy::class);
    expect(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class);
});

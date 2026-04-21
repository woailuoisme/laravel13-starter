<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\AdminUser;

abstract class AuthorizesShieldPermissions
{
    protected function allows(AdminUser $user, string $ability): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->can($ability);
    }

    protected function permission(string $ability, string $subject): string
    {
        return "{$ability}:{$subject}";
    }

    protected function isSuperAdmin(AdminUser $user): bool
    {
        if (! (bool) config('filament-shield.super_admin.enabled', true)) {
            return false;
        }

        return $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }
}

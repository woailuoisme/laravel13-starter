<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\ManageSystemSettings;
use App\Filament\Admin\Resources\AdminUsers\AdminUserResource;
use App\Filament\Admin\Resources\Users\UserResource;

it('translates filament navigation labels for chinese and english', function (): void {
    app()->setLocale('zh_CN');

    expect(UserResource::getNavigationLabel())->toBe('用户')
        ->and(AdminUserResource::getNavigationLabel())->toBe('后台用户')
        ->and(ManageSystemSettings::getNavigationLabel())->toBe('系统设置')
        ->and(__('admin.resources.users.fields.email'))->toBe('邮箱地址')
        ->and(__('admin.resources.admin_users.fields.avatar_url'))->toBe('头像地址')
        ->and(__('admin.pages.manage_ecommerce.fields.close_reason'))->toBe('关闭原因');

    app()->setLocale('en');

    expect(UserResource::getNavigationLabel())->toBe('Users')
        ->and(AdminUserResource::getNavigationLabel())->toBe('Admin Users')
        ->and(ManageSystemSettings::getNavigationLabel())->toBe('System Settings')
        ->and(__('admin.resources.users.fields.email'))->toBe('Email address')
        ->and(__('admin.resources.admin_users.fields.avatar_url'))->toBe('Avatar URL')
        ->and(__('admin.pages.manage_ecommerce.fields.close_reason'))->toBe('Close reason');
});

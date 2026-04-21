<?php

namespace App\Filament\Admin\Resources\AdminUsers\Schemas;

use App\Models\AdminUser;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AdminUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('username')
                    ->label(__('admin.resources.admin_users.fields.username')),
                TextEntry::make('name')
                    ->label(__('admin.resources.admin_users.fields.name')),
                TextEntry::make('email')
                    ->label(__('admin.resources.admin_users.fields.email')),
                TextEntry::make('phone')
                    ->label(__('admin.resources.admin_users.fields.phone'))
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->label(__('admin.resources.admin_users.fields.is_active'))
                    ->boolean(),
                TextEntry::make('last_login_at')
                    ->label(__('admin.resources.admin_users.fields.last_login_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('last_login_ip')
                    ->label(__('admin.resources.admin_users.fields.last_login_ip'))
                    ->placeholder('-'),
                TextEntry::make('avatar_url')
                    ->label(__('admin.resources.admin_users.fields.avatar_url'))
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label(__('admin.resources.admin_users.fields.created_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label(__('admin.resources.admin_users.fields.updated_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->label(__('admin.resources.admin_users.fields.deleted_at'))
                    ->dateTime()
                    ->visible(fn (AdminUser $record): bool => $record->trashed()),
            ]);
    }
}

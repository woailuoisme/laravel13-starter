<?php

namespace App\Filament\Admin\Resources\AdminUsers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->label(__('admin.resources.admin_users.fields.username'))
                    ->required(),
                TextInput::make('name')
                    ->label(__('admin.resources.admin_users.fields.name'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('admin.resources.admin_users.fields.email'))
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->label(__('admin.resources.admin_users.fields.password'))
                    ->password()
                    ->required(),
                TextInput::make('phone')
                    ->label(__('admin.resources.admin_users.fields.phone'))
                    ->tel(),
                Toggle::make('is_active')
                    ->label(__('admin.resources.admin_users.fields.is_active'))
                    ->required(),
                DateTimePicker::make('last_login_at')
                    ->label(__('admin.resources.admin_users.fields.last_login_at')),
                TextInput::make('last_login_ip')
                    ->label(__('admin.resources.admin_users.fields.last_login_ip')),
                TextInput::make('avatar_url')
                    ->label(__('admin.resources.admin_users.fields.avatar_url'))
                    ->url(),
            ]);
    }
}

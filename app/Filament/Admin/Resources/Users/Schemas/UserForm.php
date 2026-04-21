<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.resources.users.fields.name'))
                    ->required(),
                TextInput::make('username'),
                TextInput::make('email')
                    ->label(__('admin.resources.users.fields.email'))
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->label(__('admin.resources.users.fields.phone'))
                    ->tel(),
                TextInput::make('avatar')
                    ->label(__('admin.resources.users.fields.avatar')),
                DatePicker::make('birthday')
                    ->label(__('admin.resources.users.fields.birthday')),
                TextInput::make('gender')
                    ->label(__('admin.resources.users.fields.gender'))
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('bio')
                    ->label(__('admin.resources.users.fields.bio')),
                DateTimePicker::make('email_verified_at')
                    ->label(__('admin.resources.users.fields.email_verified_at')),
                TextInput::make('password')
                    ->label(__('admin.resources.users.fields.password'))
                    ->password()
                    ->required(),
                TextInput::make('open_id')
                    ->label(__('admin.resources.users.fields.open_id')),
                TextInput::make('github_id')
                    ->label(__('admin.resources.users.fields.github_id')),
                TextInput::make('google_id')
                    ->label(__('admin.resources.users.fields.google_id')),
                TextInput::make('nickname')
                    ->label(__('admin.resources.users.fields.nickname')),
                TextInput::make('telephone')
                    ->label(__('admin.resources.users.fields.telephone'))
                    ->tel(),
                DateTimePicker::make('last_login_at')
                    ->label(__('admin.resources.users.fields.last_login_at')),
                TextInput::make('last_login_ip')
                    ->label(__('admin.resources.users.fields.last_login_ip')),
                TextInput::make('stripe_id')
                    ->label(__('admin.resources.users.fields.stripe_id')),
                TextInput::make('pm_type')
                    ->label(__('admin.resources.users.fields.pm_type')),
                TextInput::make('pm_last_four')
                    ->label(__('admin.resources.users.fields.pm_last_four')),
                DateTimePicker::make('trial_ends_at')
                    ->label(__('admin.resources.users.fields.trial_ends_at')),
            ]);
    }
}

<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('admin.resources.users.fields.name')),
                TextEntry::make('username')
                    ->label(__('admin.resources.users.fields.username'))
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label(__('admin.resources.users.fields.email')),
                TextEntry::make('phone')
                    ->label(__('admin.resources.users.fields.phone'))
                    ->placeholder('-'),
                TextEntry::make('avatar')
                    ->label(__('admin.resources.users.fields.avatar'))
                    ->placeholder('-'),
                TextEntry::make('birthday')
                    ->label(__('admin.resources.users.fields.birthday'))
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('gender')
                    ->label(__('admin.resources.users.fields.gender'))
                    ->numeric(),
                TextEntry::make('bio')
                    ->label(__('admin.resources.users.fields.bio'))
                    ->placeholder('-'),
                TextEntry::make('email_verified_at')
                    ->label(__('admin.resources.users.fields.email_verified_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('open_id')
                    ->label(__('admin.resources.users.fields.open_id'))
                    ->placeholder('-'),
                TextEntry::make('github_id')
                    ->label(__('admin.resources.users.fields.github_id'))
                    ->placeholder('-'),
                TextEntry::make('google_id')
                    ->label(__('admin.resources.users.fields.google_id'))
                    ->placeholder('-'),
                TextEntry::make('nickname')
                    ->label(__('admin.resources.users.fields.nickname'))
                    ->placeholder('-'),
                TextEntry::make('telephone')
                    ->label(__('admin.resources.users.fields.telephone'))
                    ->placeholder('-'),
                TextEntry::make('last_login_at')
                    ->label(__('admin.resources.users.fields.last_login_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('last_login_ip')
                    ->label(__('admin.resources.users.fields.last_login_ip'))
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label(__('admin.resources.users.fields.created_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label(__('admin.resources.users.fields.updated_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->label(__('admin.resources.users.fields.deleted_at'))
                    ->dateTime()
                    ->visible(fn (User $record): bool => $record->trashed()),
                TextEntry::make('stripe_id')
                    ->label(__('admin.resources.users.fields.stripe_id'))
                    ->placeholder('-'),
                TextEntry::make('pm_type')
                    ->label(__('admin.resources.users.fields.pm_type'))
                    ->placeholder('-'),
                TextEntry::make('pm_last_four')
                    ->label(__('admin.resources.users.fields.pm_last_four'))
                    ->placeholder('-'),
                TextEntry::make('trial_ends_at')
                    ->label(__('admin.resources.users.fields.trial_ends_at'))
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

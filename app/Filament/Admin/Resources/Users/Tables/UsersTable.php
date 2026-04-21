<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.resources.users.fields.name'))
                    ->searchable(),
                TextColumn::make('username')
                    ->label(__('admin.resources.users.fields.username'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin.resources.users.fields.email'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('admin.resources.users.fields.phone'))
                    ->searchable(),
                TextColumn::make('avatar')
                    ->label(__('admin.resources.users.fields.avatar'))
                    ->searchable(),
                TextColumn::make('birthday')
                    ->label(__('admin.resources.users.fields.birthday'))
                    ->date()
                    ->sortable(),
                TextColumn::make('gender')
                    ->label(__('admin.resources.users.fields.gender'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bio')
                    ->label(__('admin.resources.users.fields.bio'))
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->label(__('admin.resources.users.fields.email_verified_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('open_id')
                    ->label(__('admin.resources.users.fields.open_id'))
                    ->searchable(),
                TextColumn::make('github_id')
                    ->label(__('admin.resources.users.fields.github_id'))
                    ->searchable(),
                TextColumn::make('google_id')
                    ->label(__('admin.resources.users.fields.google_id'))
                    ->searchable(),
                TextColumn::make('nickname')
                    ->label(__('admin.resources.users.fields.nickname'))
                    ->searchable(),
                TextColumn::make('telephone')
                    ->label(__('admin.resources.users.fields.telephone'))
                    ->searchable(),
                TextColumn::make('last_login_at')
                    ->label(__('admin.resources.users.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_login_ip')
                    ->label(__('admin.resources.users.fields.last_login_ip'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.resources.users.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin.resources.users.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('admin.resources.users.fields.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stripe_id')
                    ->label(__('admin.resources.users.fields.stripe_id'))
                    ->searchable(),
                TextColumn::make('pm_type')
                    ->label(__('admin.resources.users.fields.pm_type'))
                    ->searchable(),
                TextColumn::make('pm_last_four')
                    ->label(__('admin.resources.users.fields.pm_last_four'))
                    ->searchable(),
                TextColumn::make('trial_ends_at')
                    ->label(__('admin.resources.users.fields.trial_ends_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Admin\Resources\AdminUsers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label(__('admin.resources.admin_users.fields.username'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('admin.resources.admin_users.fields.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin.resources.admin_users.fields.email'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('admin.resources.admin_users.fields.phone'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('admin.resources.admin_users.fields.is_active'))
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label(__('admin.resources.admin_users.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_login_ip')
                    ->label(__('admin.resources.admin_users.fields.last_login_ip')),
                TextColumn::make('avatar_url')
                    ->label(__('admin.resources.admin_users.fields.avatar_url'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.resources.admin_users.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin.resources.admin_users.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('admin.resources.admin_users.fields.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

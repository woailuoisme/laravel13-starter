<?php

namespace App\Filament\Admin\Resources\AdminUsers;

use App\Enums\FilamentNavigationGroup;
use App\Filament\Admin\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\Admin\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\Admin\Resources\AdminUsers\Pages\ViewAdminUser;
use App\Filament\Admin\Resources\AdminUsers\Schemas\AdminUserForm;
use App\Filament\Admin\Resources\AdminUsers\Schemas\AdminUserInfolist;
use App\Filament\Admin\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Models\AdminUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = FilamentNavigationGroup::BackendManagement;
    protected static ?int $navigationSort = -30;

    public static function getModelLabel(): string
    {
        return __('admin.resources.admin_users.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.admin_users.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.admin_users');
    }

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdminUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'view' => ViewAdminUser::route('/{record}'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

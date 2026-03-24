<?php

namespace App\Filament\Admin\Resources\AdminUsers\Pages;

use App\Filament\Admin\Resources\AdminUsers\AdminUserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminUser extends ViewRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

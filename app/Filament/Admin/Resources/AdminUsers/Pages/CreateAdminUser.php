<?php

namespace App\Filament\Admin\Resources\AdminUsers\Pages;

use App\Filament\Admin\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;
}

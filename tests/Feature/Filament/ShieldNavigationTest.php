<?php

declare(strict_types=1);

use App\Models\AdminUser;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('seeds shield roles and permissions so the role page is reachable', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->where('name', 'super_admin')->exists())->toBeTrue()
        ->and(Permission::query()->exists())->toBeTrue();

    $admin = AdminUser::query()->where('email', 'admin@example.com')->firstOrFail();

    actingAs($admin, 'filament');

    $this->get('/admin/shield/roles')->assertSuccessful();
});

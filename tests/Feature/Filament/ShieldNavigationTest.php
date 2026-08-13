<?php

declare(strict_types=1);

use App\Models\AdminUser;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

it('seeds shield roles and permissions so the role page is reachable', function (): void {
    seed(DatabaseSeeder::class);

    expect(Role::query()->where('name', 'super_admin')->exists())->toBeTrue()
        ->and(Permission::query()->exists())
        ->toBeTrue();

    $admin = AdminUser::query()->where('email', 'admin@example.com')->firstOrFail();

    actingAs($admin, 'filament');

    get('/admin/shield/roles')->assertSuccessful();
});

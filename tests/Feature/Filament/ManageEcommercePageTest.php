<?php

declare(strict_types=1);

use App\Models\AdminUser;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('opens the ecommerce settings page for an authenticated admin user', function (): void {
    /** @var AdminUser $admin */
    $admin = AdminUser::factory()->create([
        'is_active' => true,
    ]);

    actingAs($admin, 'filament');

    get('/admin/manage-system-settings')->assertSuccessful();
});

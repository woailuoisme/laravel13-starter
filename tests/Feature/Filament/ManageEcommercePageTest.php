<?php

declare(strict_types=1);

use App\Models\AdminUser;

use function Pest\Laravel\actingAs;

it('opens the ecommerce settings page for an authenticated admin user', function (): void {
    $admin = AdminUser::factory()->create([
        'is_active' => true,
    ]);

    actingAs($admin, 'filament');

    $this->get('/admin/manage-ecommerce')->assertSuccessful();
});

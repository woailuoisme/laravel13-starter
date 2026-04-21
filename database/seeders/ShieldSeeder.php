<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--option' => 'policies_and_permissions',
            '--panel' => 'admin',
            '--no-interaction' => true,
        ]);

        $adminUserId = AdminUser::query()
            ->where('email', 'admin@example.com')
            ->value('id')
            ?? AdminUser::query()->orderBy('id')->value('id');

        if ($adminUserId === null) {
            return;
        }

        Artisan::call('shield:super-admin', [
            '--user' => $adminUserId,
            '--panel' => 'admin',
            '--no-interaction' => true,
        ]);
    }
}

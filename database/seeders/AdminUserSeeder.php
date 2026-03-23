<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建一个固定的超级管理员
        AdminUser::factory()->create([
            'username' => 'admin',
            'name' => '超级管理员',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'is_active' => true,
        ]);

        // 创建 5 个随机管理员
        AdminUser::factory()->count(5)->create();
    }
}

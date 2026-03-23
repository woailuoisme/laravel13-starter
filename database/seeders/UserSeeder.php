<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建一个固定的测试账号
        User::factory()->create([
            'name' => '测试用户',
            'username' => 'user',
            'email' => 'user@example.com',
            'phone' => '13800138000',
            'password' => Hash::make('password'),
        ]);

        // 创建 20 个随机用户
        User::factory()->count(20)->create();
    }
}

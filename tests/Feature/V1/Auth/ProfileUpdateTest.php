<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows a user to keep the same telephone number when updating the profile', function (): void {
    $user = User::factory()->create([
        'email' => 'profile-update@example.com',
        'telephone' => '13800138000',
        'nickname' => 'profile-update',
        'password' => Hash::make('password123'),
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/profile', [
            'nickname' => 'updated-profile',
            'telephone' => '13800138000',
            'gender' => 'male',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.nickname', 'updated-profile');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'nickname' => 'updated-profile',
        'telephone' => '13800138000',
    ]);
});

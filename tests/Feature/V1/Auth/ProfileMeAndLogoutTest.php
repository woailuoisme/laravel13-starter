<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('retrieves the authenticated user profile via me endpoint', function (): void {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'me-test@example.com',
        'nickname' => 'me-tester',
        'password' => Hash::make('password123'),
    ]);

    $token = (string) auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'me-test@example.com')
        ->assertJsonPath('data.nickname', 'me-tester');
});

it('logs out the authenticated user', function (): void {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'logout-test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = (string) auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('refreshes the authentication token', function (): void {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'refresh-test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = (string) auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token']]);
});

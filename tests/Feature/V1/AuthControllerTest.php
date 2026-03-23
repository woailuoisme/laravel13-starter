<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    $this->user = User::factory()->create([
        'nickname' => 'testuser',
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
});

it('can login with correct credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'nickname' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'nickname', 'email', 'avatar'],
            ],
            'success',
            'message',
        ]);
});

it('fails to login with incorrect credentials', function (string $nickname, string $password) {
    $this->postJson('/api/v1/auth/login', [
        'nickname' => $nickname,
        'password' => $password,
    ])->assertUnauthorized();
})->with([
    'invalid password' => ['test@example.com', 'wrongpassword'],
    'non-existent email' => ['wrong@example.com', 'password123'],
]);

it('fails to login with missing credentials', function (string $nickname, string $password) {
    $this->postJson('/api/v1/auth/login', [
        'nickname' => $nickname,
        'password' => $password,
    ])->assertUnprocessable();
})->with([
    'empty credentials' => ['', ''],
    'missing password' => ['test@example.com', ''],
    'missing nickname' => ['', 'password123'],
]);

it('can fetch current user profile when authenticated', function () {
    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/v1/auth/me');

    $response->assertSuccessful()
        ->assertJsonPath('data.email', 'test@example.com');
});

it('can handle socialite redirect', function () {
    Socialite::shouldReceive('driver->stateless->redirect->getTargetUrl')
        ->andReturn('https://provider.com/oauth/authorize');

    $response = $this->getJson('/api/v1/auth/github/redirect');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'url' => 'https://provider.com/oauth/authorize',
        ]);
});

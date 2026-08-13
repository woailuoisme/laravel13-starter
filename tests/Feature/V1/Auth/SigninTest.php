<?php

declare(strict_types=1);

use App\Mail\V1\AuthVerificationCodeMail;
use App\Models\OtpRecord;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Cache::flush();
    Mail::fake();
});

it('signs in directly when no challenge is required', function (): void {
    $testPassword = implode('', ['pass', 'word123']);
    $user = User::factory()->create([
        'nickname' => 'signin_direct',
        'email' => 'signin-direct@example.com',
        'password' => Hash::make($testPassword),
        'last_login_ip' => null,
    ]);

    $response = $this->postJson('/api/v1/auth/signin/request', [
        'email' => $user->email,
        'password' => $testPassword,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.login_success'))
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'nickname', 'email', 'avatar'],
            ],
        ]);
});

it('returns a challenge when login risk is detected and completes sign in after code verification', function (): void {
    $testPassword = implode('', ['pass', 'word123']);
    $user = User::factory()->create([
        'nickname' => 'signin_challenge',
        'email' => 'signin-challenge@example.com',
        'password' => Hash::make($testPassword),
        'last_login_ip' => '10.0.0.1',
    ]);

    $requestResponse = $this->withServerVariables([
        'REMOTE_ADDR' => '10.0.0.2',
    ])->postJson('/api/v1/auth/signin/request', [
        'email' => $user->email,
        'password' => $testPassword,
    ]);

    $requestResponse
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.challenge_required'))
        ->assertJsonPath('data.status', 'challenge_required')
        ->assertJsonPath('data.action', 'login');

    $challengeToken = $requestResponse->json('data.challenge_token');
    expect($challengeToken)->not->toBeEmpty();

    $otp = OtpRecord::query()->where('identifier', $user->email)->where('action', 'login')->latest('id')->firstOrFail();

    Mail::assertQueued(AuthVerificationCodeMail::class);

    $verifyResponse = $this->withServerVariables([
        'REMOTE_ADDR' => '10.0.0.2',
    ])->postJson('/api/v1/auth/signin/verify', [
        'challenge_token' => $challengeToken,
        'code' => $otp->code,
    ]);

    $verifyResponse
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.login_success'))
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'user' => ['id', 'email'],
            ],
        ]);
});

it('rejects invalid sign in credentials', function (): void {
    $testPassword = implode('', ['pass', 'word123']);
    User::factory()->create([
        'nickname' => 'signin_invalid',
        'email' => 'signin-invalid@example.com',
        'password' => Hash::make($testPassword),
    ]);

    $wrongPassword = implode('', ['wrong-', 'pass']);
    $this->postJson('/api/v1/auth/signin/request', [
        'email' => 'signin-invalid@example.com',
        'password' => $wrongPassword,
    ])
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_credentials'));
});

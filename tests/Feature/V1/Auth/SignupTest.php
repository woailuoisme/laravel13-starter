<?php

declare(strict_types=1);

use App\Mail\V1\AuthVerificationCodeMail;
use App\Models\OtpRecord;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Cache::flush();
    Mail::fake();
});

it('starts signup without creating a user and sends a verification code', function (): void {
    $response = $this->postJson('/api/v1/auth/signup/request', [
        'email' => 'signup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'code_sent')
        ->assertJsonPath('data.action', 'register')
        ->assertJsonPath('data.email', 'signup@example.com');

    expect(User::query()->where('email', 'signup@example.com')->exists())->toBeFalse();

    $otp = OtpRecord::query()
        ->where('identifier', 'signup@example.com')
        ->where('action', 'register')
        ->latest('id')
        ->first();

    expect($otp)->not->toBeNull();
    Mail::assertQueued(AuthVerificationCodeMail::class);
});

it('verifies signup code, creates the user, and returns a jwt payload', function (): void {
    $this->postJson('/api/v1/auth/signup/request', [
        'email' => 'verify-signup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertOk();

    $otp = OtpRecord::query()
        ->where('identifier', 'verify-signup@example.com')
        ->where('action', 'register')
        ->latest('id')
        ->firstOrFail();

    $response = $this->postJson('/api/v1/auth/signup/verify', [
        'email' => 'verify-signup@example.com',
        'code' => $otp->code,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.register_success'))
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'nickname', 'email', 'avatar'],
            ],
        ]);

    $user = User::query()->where('email', 'verify-signup@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user?->email_verified_at)->not->toBeNull()
        ->and($otp->fresh()?->used_at)->not->toBeNull();
});

it('throttles signup code resend requests within sixty seconds', function (): void {
    $this->postJson('/api/v1/auth/signup/request', [
        'email' => 'resend-signup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertOk();

    $this->postJson('/api/v1/auth/code/resend', [
        'email' => 'resend-signup@example.com',
        'action' => 'register',
    ])->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.verification_code_throttled', ['seconds' => 60]));
});

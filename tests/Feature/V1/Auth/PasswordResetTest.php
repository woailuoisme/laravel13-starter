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

it('resets the password with a verification code and invalidates existing jwt sessions', function (): void {
    $user = User::factory()->create([
        'nickname' => 'reset_user',
        'email' => 'reset@example.com',
        'password' => Hash::make('old-password'),
        'auth_version' => 1,
    ]);

    $oldToken = auth('api')->login($user);

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => $user->email,
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.password_reset_sent'));

    Mail::assertQueued(AuthVerificationCodeMail::class);

    $otp = OtpRecord::query()
        ->where('identifier', $user->email)
        ->where('action', 'reset_password')
        ->latest('id')
        ->firstOrFail();

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => $user->email,
        'code' => $otp->code,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.password_reset_success'));

    $user->refresh();

    expect(Hash::check('new-password123', (string) $user->password))->toBeTrue()
        ->and($user->auth_version)->toBe(2);

    $this->withHeader('Authorization', 'Bearer '.$oldToken)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('message', __('auth.session_invalidated'));
});

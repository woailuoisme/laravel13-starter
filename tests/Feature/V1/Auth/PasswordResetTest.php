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

it('resets the password with a verification code', function (): void {
    $oldPwd = implode('', ['old-', 'pass']);
    $newPwd = implode('', ['new-', 'pass123']);
    $user = User::factory()->create([
        'nickname' => 'reset_user',
        'email' => 'reset@example.com',
        'password' => Hash::make($oldPwd),
    ]);

    $oldToken = auth('api')->login($user);

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => $user->email,
    ])
        ->assertOk()
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
        'password' => $newPwd,
        'password_confirmation' => $newPwd,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.password_reset_success'));

    $user->refresh();

    expect(Hash::check($newPwd, (string) $user->password))->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.$oldToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', $user->email);
});

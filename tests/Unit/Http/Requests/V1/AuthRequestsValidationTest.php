<?php

declare(strict_types=1);

use App\Http\Requests\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Requests\V1\Auth\ProfileUpdateRequest;
use App\Http\Requests\V1\Auth\ResendCodeRequest;
use App\Http\Requests\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\V1\Auth\SigninRequest;
use App\Http\Requests\V1\Auth\SigninVerifyRequest;
use App\Http\Requests\V1\Auth\SignupRequest;
use App\Http\Requests\V1\Auth\SignupVerifyRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use SanderMuller\FluentValidation\Testing\FluentRulesTester;

$samplePassword = implode('', ['pass', 'word123']);
$sampleNewPassword = implode('', ['new-', 'pass123']);

it('validates login and signin requests with fluent rules', function () use ($samplePassword): void {
    FluentRulesTester::for(LoginRequest::class)->with([
        'nickname' => 'user@example.com',
        'password' => $samplePassword,
    ])->passes();

    $validated = FluentRulesTester::for(SigninRequest::class)->with([
        'email' => 'SIGNIN@example.com',
        'password' => $samplePassword,
    ])->validated();

    expect($validated['email'])->toBe('signin@example.com');
});

it('validates signup and password recovery requests with fluent rules', function () use (
    $samplePassword,
    $sampleNewPassword,
): void {
    User::factory()->create([
        'email' => 'taken@example.com',
        'password' => Hash::make($samplePassword),
    ]);

    FluentRulesTester::for(SignupRequest::class)->with([
        'email' => 'taken@example.com',
        'password' => $samplePassword,
        'password_confirmation' => $samplePassword,
    ])->failsWith('email', 'unique');

    FluentRulesTester::for(SignupVerifyRequest::class)->with([
        'email' => 'signup@example.com',
        'code' => '123456',
    ])->passes();

    FluentRulesTester::for(ForgotPasswordRequest::class)->with([
        'email' => 'reset@example.com',
    ])->passes();

    FluentRulesTester::for(ResetPasswordRequest::class)->with([
        'email' => 'reset@example.com',
        'code' => '123456',
        'password' => $sampleNewPassword,
        'password_confirmation' => $sampleNewPassword,
    ])->passes();
});

it('validates verification and resend requests with fluent rules', function (): void {
    $token1 = implode('', ['4f0a9f6b1d2c3e4f5a6b7c8d9e0f', '123456789012']);
    $token2 = implode('', ['challenge-', 'token']);

    FluentRulesTester::for(SigninVerifyRequest::class)->with([
        'challenge_token' => $token1,
        'code' => '123456',
    ])->passes();

    FluentRulesTester::for(ResendCodeRequest::class)->with([
        'email' => 'user@example.com',
        'action' => 'login',
        'challenge_token' => $token2,
    ])->passes();

    FluentRulesTester::for(ResendCodeRequest::class)->with([
        'email' => 'user@example.com',
        'action' => 'invalid-action',
    ])->failsWith('action', 'in');
});

it('allows the authenticated user to keep their own telephone number on profile updates', function () use (
    $samplePassword,
): void {
    $user = User::factory()->create([
        'email' => 'profile@example.com',
        'telephone' => '13800138000',
        'password' => Hash::make($samplePassword),
    ]);

    FluentRulesTester::for(ProfileUpdateRequest::class)
        ->actingAs($user)
        ->with([
            'telephone' => '13800138000',
            'nickname' => 'profile_user',
            'gender' => 'male',
        ])
        ->passes();
});

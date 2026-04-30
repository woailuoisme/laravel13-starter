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

it('validates login and signin requests with fluent rules', function (): void {
    FluentRulesTester::for(LoginRequest::class)
        ->with([
            'nickname' => 'user@example.com',
            'password' => 'password123',
        ])
        ->passes();

    $validated = FluentRulesTester::for(SigninRequest::class)
        ->with([
            'email' => 'SIGNIN@example.com',
            'password' => 'password123',
        ])
        ->validated();

    expect($validated['email'])->toBe('signin@example.com');
});

it('validates signup and password recovery requests with fluent rules', function (): void {
    User::factory()->create([
        'email' => 'taken@example.com',
        'password' => Hash::make('password123'),
    ]);

    FluentRulesTester::for(SignupRequest::class)
        ->with([
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->failsWith('email', 'unique');

    FluentRulesTester::for(SignupVerifyRequest::class)
        ->with([
            'email' => 'signup@example.com',
            'code' => '123456',
        ])
        ->passes();

    FluentRulesTester::for(ForgotPasswordRequest::class)
        ->with([
            'email' => 'reset@example.com',
        ])
        ->passes();

    FluentRulesTester::for(ResetPasswordRequest::class)
        ->with([
            'email' => 'reset@example.com',
            'code' => '123456',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
        ->passes();
});

it('validates verification and resend requests with fluent rules', function (): void {
    FluentRulesTester::for(SigninVerifyRequest::class)
        ->with([
            'challenge_token' => '4f0a9f6b1d2c3e4f5a6b7c8d9e0f123456789012',
            'code' => '123456',
        ])
        ->passes();

    FluentRulesTester::for(ResendCodeRequest::class)
        ->with([
            'email' => 'user@example.com',
            'action' => 'login',
            'challenge_token' => 'challenge-token',
        ])
        ->passes();

    FluentRulesTester::for(ResendCodeRequest::class)
        ->with([
            'email' => 'user@example.com',
            'action' => 'invalid-action',
        ])
        ->failsWith('action', 'in');
});

it('allows the authenticated user to keep their own telephone number on profile updates', function (): void {
    $user = User::factory()->create([
        'email' => 'profile@example.com',
        'telephone' => '13800138000',
        'password' => Hash::make('password123'),
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

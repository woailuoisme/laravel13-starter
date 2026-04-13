<?php

declare(strict_types=1);

use App\Mail\V1\AuthVerificationCodeMail;
use App\Models\OtpRecord;
use App\Models\User;
use App\Services\Auth\AuthFlowService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Cache::flush();
    Mail::fake();
});

it('creates and verifies a sign in challenge when forced by risk policy', function (): void {
    $user = User::factory()->create([
        'nickname' => 'unit_signin',
        'email' => 'unit-signin@example.com',
        'password' => Hash::make('password123'),
        'last_login_ip' => '192.168.0.1',
    ]);

    /** @var AuthFlowService $service */
    $service = app(AuthFlowService::class);

    $requestResult = $service->requestSignin($user->email, 'password123', '192.168.0.2', false);

    expect($requestResult['status'])->toBe('challenge_required')
        ->and($requestResult['challenge_token'])->not->toBeEmpty();

    Mail::assertQueued(AuthVerificationCodeMail::class);

    $otp = OtpRecord::query()
        ->where('identifier', $user->email)
        ->where('action', 'login')
        ->latest('id')
        ->firstOrFail();

    $authenticatedUser = $service->verifySignin($requestResult['challenge_token'], $otp->code, '192.168.0.2');

    expect($authenticatedUser->is($user))->toBeTrue()
        ->and($authenticatedUser->fresh()?->last_login_ip)->toBe('192.168.0.2');
});

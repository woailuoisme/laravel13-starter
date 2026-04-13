<?php

declare(strict_types=1);

use App\Mail\V1\AuthVerificationCodeMail;
use App\Models\OtpRecord;
use App\Models\User;
use App\Services\Auth\AuthFlowService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Cache::flush();
    Mail::fake();
});

it('creates a pending signup challenge and finalizes user creation after code verification', function (): void {
    /** @var AuthFlowService $service */
    $service = app(AuthFlowService::class);

    $requestResult = $service->requestSignup('unit-signup@example.com', 'password123', '127.0.0.1');

    expect($requestResult['status'])->toBe('code_sent')
        ->and($requestResult['action'])->toBe('register');

    Mail::assertQueued(AuthVerificationCodeMail::class);

    $otp = OtpRecord::query()
        ->where('identifier', 'unit-signup@example.com')
        ->where('action', 'register')
        ->latest('id')
        ->firstOrFail();

    $user = $service->verifySignup('unit-signup@example.com', $otp->code, '127.0.0.1');

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('unit-signup@example.com')
        ->and(Cache::get('auth:signup:unit-signup@example.com'))->toBeNull();
});

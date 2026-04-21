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

it('increments auth version and updates password after a valid reset code', function (): void {
    $user = User::factory()->create([
        'nickname' => 'unit_reset',
        'email' => 'unit-reset@example.com',
        'password' => Hash::make('old-password'),
    ]);

    /** @var AuthFlowService $service */
    $service = app(AuthFlowService::class);

    $requestResult = $service->requestPasswordReset($user->email);

    expect($requestResult['status'])->toBe('code_sent')
        ->and($requestResult['action'])->toBe('reset_password');

    Mail::assertQueued(AuthVerificationCodeMail::class);

    $otp = OtpRecord::query()
        ->where('identifier', $user->email)
        ->where('action', 'reset_password')
        ->latest('id')
        ->firstOrFail();

    $service->resetPassword($user->email, $otp->code, 'new-password123');

    $user->refresh();

    expect(Hash::check('new-password123', (string) $user->password))->toBeTrue();
});

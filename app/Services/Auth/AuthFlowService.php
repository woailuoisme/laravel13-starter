<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\V1\AuthVerificationCodeMail;
use App\Models\OtpRecord;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthFlowService
{
    private const OTP_TTL_SECONDS = 600;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const SIGNUP_CACHE_PREFIX = 'auth:signup:';
    private const SIGNIN_CACHE_PREFIX = 'auth:signin:challenge:';
    private const RESET_CACHE_PREFIX = 'auth:password-reset:';

    public function requestSignup(string $email, string $password, ?string $ip): array
    {
        $this->enforceCooldown($email, 'register');

        Cache::put($this->signupCacheKey($email), [
            'email' => $email,
            'password_hash' => Hash::make($password),
            'nickname_seed' => Str::before($email, '@'),
            'requested_ip' => $ip,
        ], now()->addSeconds(self::OTP_TTL_SECONDS));

        $otp = $this->issueOtp($email, 'register');

        return [
            'status' => 'code_sent',
            'action' => 'register',
            'email' => $email,
            'resend_in' => $this->secondsUntilResendAvailable($otp->created_at?->timestamp),
        ];
    }

    public function verifySignup(string $email, string $code, ?string $ip): User
    {
        $pendingSignup = Cache::get($this->signupCacheKey($email));

        if (!is_array($pendingSignup)) {
            throw new HttpException(410, __('auth.signup_context_expired'));
        }

        $otp = $this->verifyOtp($email, 'register', $code);

        $user = DB::transaction(function () use ($email, $pendingSignup, $ip, $otp): User {
            $nickname = $this->makeUniqueValue($pendingSignup['nickname_seed'], 'nickname');
            $displayName = $this->makeUniqueValue($pendingSignup['nickname_seed'], 'name');

            $user = User::create([
                'name' => $displayName,
                'nickname' => $nickname,
                'email' => $email,
                'password' => $pendingSignup['password_hash'],
                'email_verified_at' => now(),
                'last_login_at' => now(),
                'last_login_ip' => $ip,
            ]);

            $otp->forceFill([
                'user_id' => $user->id,
                'used_at' => now(),
            ])->save();

            return $user;
        });

        Cache::forget($this->signupCacheKey($email));

        return $user;
    }

    public function requestSignin(string $email, string $password, ?string $ip, bool $forceChallenge = false): array
    {
        $user = User::query()->where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new HttpException(401, __('auth.invalid_credentials'));
        }

        if (!$this->shouldRequireChallenge($user, $ip, $forceChallenge)) {
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $ip,
            ])->save();

            return [
                'status' => 'authenticated',
                'user' => $user,
            ];
        }

        $this->enforceCooldown($email, 'login');

        $challengeToken = Str::random(40);
        Cache::put($this->signinCacheKey($challengeToken), [
            'user_id' => $user->id,
            'email' => $user->email,
            'requested_ip' => $ip,
            'risk_reason' => 'new_ip',
        ], now()->addSeconds(self::OTP_TTL_SECONDS));

        $otp = $this->issueOtp($email, 'login', $user);

        return [
            'status' => 'challenge_required',
            'action' => 'login',
            'email' => $email,
            'challenge_token' => $challengeToken,
            'resend_in' => $this->secondsUntilResendAvailable($otp->created_at?->timestamp),
        ];
    }

    public function verifySignin(string $challengeToken, string $code, ?string $ip): User
    {
        $challenge = Cache::get($this->signinCacheKey($challengeToken));

        if (!is_array($challenge)) {
            throw new HttpException(410, __('auth.challenge_expired'));
        }

        $otp = $this->verifyOtp((string) $challenge['email'], 'login', $code);
        $user = User::query()->findOrFail((int) $challenge['user_id']);

        $otp->forceFill([
            'user_id' => $user->id,
            'used_at' => now(),
        ])->save();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        Cache::forget($this->signinCacheKey($challengeToken));

        return $user;
    }

    public function requestPasswordReset(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            return [
                'status' => 'code_sent',
                'action' => 'reset_password',
                'email' => $email,
                'resend_in' => 0,
            ];
        }

        $this->enforceCooldown($email, 'reset_password');

        Cache::put($this->resetCacheKey($email), [
            'user_id' => $user->id,
            'email' => $email,
        ], now()->addSeconds(self::OTP_TTL_SECONDS));

        $otp = $this->issueOtp($email, 'reset_password', $user);

        return [
            'status' => 'code_sent',
            'action' => 'reset_password',
            'email' => $email,
            'resend_in' => $this->secondsUntilResendAvailable($otp->created_at?->timestamp),
        ];
    }

    public function resetPassword(string $email, string $code, string $password): void
    {
        $session = Cache::get($this->resetCacheKey($email));

        if (!is_array($session)) {
            throw new HttpException(410, __('auth.password_reset_expired'));
        }

        $otp = $this->verifyOtp($email, 'reset_password', $code);
        $user = User::query()->findOrFail((int) $session['user_id']);

        DB::transaction(function () use ($user, $password, $otp): void {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $otp->forceFill([
                'user_id' => $user->id,
                'used_at' => now(),
            ])->save();
        });

        Cache::forget($this->resetCacheKey($email));
    }

    public function resendCode(string $email, string $action, ?string $challengeToken = null): array
    {
        return match ($action) {
            'register' => $this->requestSignupResend($email),
            'login' => $this->requestSigninResend($email, $challengeToken),
            'reset_password' => $this->requestPasswordReset($email),
            default => throw new HttpException(422, __('auth.unsupported_action')),
        };
    }

    private function requestSignupResend(string $email): array
    {
        if (!is_array(Cache::get($this->signupCacheKey($email)))) {
            throw new HttpException(410, __('auth.signup_context_expired'));
        }

        $this->enforceCooldown($email, 'register');
        $otp = $this->issueOtp($email, 'register');

        return [
            'status' => 'code_sent',
            'action' => 'register',
            'email' => $email,
            'resend_in' => $this->secondsUntilResendAvailable($otp->created_at?->timestamp),
        ];
    }

    private function requestSigninResend(string $email, ?string $challengeToken): array
    {
        if (!$challengeToken || !is_array(Cache::get($this->signinCacheKey($challengeToken)))) {
            throw new HttpException(410, __('auth.challenge_expired'));
        }

        $this->enforceCooldown($email, 'login');
        $user = User::query()->where('email', $email)->first();
        $otp = $this->issueOtp($email, 'login', $user);

        return [
            'status' => 'code_sent',
            'action' => 'login',
            'email' => $email,
            'challenge_token' => $challengeToken,
            'resend_in' => $this->secondsUntilResendAvailable($otp->created_at?->timestamp),
        ];
    }

    private function verifyOtp(string $email, string $action, string $code): OtpRecord
    {
        $otp = OtpRecord::query()
            ->where('identifier', $email)
            ->where('type', 'email')
            ->where('action', $action)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (!$otp || $otp->code !== $code) {
            throw new HttpException(422, __('auth.verification_code_invalid'));
        }

        if ($otp->expires_at && $otp->expires_at->isPast()) {
            throw new HttpException(410, __('auth.verification_code_expired'));
        }

        return $otp;
    }

    private function issueOtp(string $email, string $action, ?User $user = null): OtpRecord
    {
        OtpRecord::query()
            ->where('identifier', $email)
            ->where('type', 'email')
            ->where('action', $action)
            ->whereNull('used_at')
            ->update([
                'expires_at' => now(),
            ]);

        $otp = OtpRecord::query()->create([
            'user_id' => $user?->id,
            'identifier' => $email,
            'type' => 'email',
            'action' => $action,
            'code' => (string) random_int(100000, 999999),
            'expires_at' => now()->addSeconds(self::OTP_TTL_SECONDS),
        ]);

        RateLimiter::hit($this->resendLimiterKey($email, $action), self::RESEND_COOLDOWN_SECONDS);

        Mail::to($email)->queue(new AuthVerificationCodeMail(
            code: $otp->code,
            action: $action,
        ));

        return $otp;
    }

    private function shouldRequireChallenge(User $user, ?string $ip, bool $forceChallenge): bool
    {
        if ($forceChallenge) {
            return true;
        }

        return $user->last_login_ip !== null && $ip !== null && $user->last_login_ip !== $ip;
    }

    private function enforceCooldown(string $email, string $action): void
    {
        $key = $this->resendLimiterKey($email, $action);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw new HttpException(429, __('auth.verification_code_throttled', [
                'seconds' => RateLimiter::availableIn($key),
            ]));
        }
    }

    private function resendLimiterKey(string $email, string $action): string
    {
        return sprintf('auth:otp:cooldown:%s:%s', $action, $email);
    }

    private function signupCacheKey(string $email): string
    {
        return self::SIGNUP_CACHE_PREFIX.$email;
    }

    private function signinCacheKey(string $challengeToken): string
    {
        return self::SIGNIN_CACHE_PREFIX.$challengeToken;
    }

    private function resetCacheKey(string $email): string
    {
        return self::RESET_CACHE_PREFIX.$email;
    }

    private function makeUniqueValue(string $seed, string $column): string
    {
        $base = Str::limit(Str::slug($seed, '_'), 24, '');
        $candidate = $base !== '' ? $base : 'user_'.Str::lower(Str::random(6));

        while (User::query()->where($column, $candidate)->exists()) {
            $candidate = $base.'_'.Str::lower(Str::random(4));
        }

        return $candidate;
    }

    private function secondsUntilResendAvailable(?int $createdAtTimestamp): int
    {
        if ($createdAtTimestamp === null) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (time() - $createdAtTimestamp));
    }
}

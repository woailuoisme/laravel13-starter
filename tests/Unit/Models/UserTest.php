<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;

describe('User model casts', function () {
    it('casts email_verified_at to a Carbon datetime instance', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);
        expect($user->email_verified_at)->toBeInstanceOf(Carbon::class);
    });

    it('casts birthday to a Carbon date instance', function () {
        $user = User::factory()->create(['birthday' => '1990-01-15']);
        expect($user->birthday)->toBeInstanceOf(Carbon::class);
    });

    it('casts last_login_at to a Carbon datetime instance', function () {
        $user = User::factory()->create(['last_login_at' => now()]);
        expect($user->last_login_at)->toBeInstanceOf(Carbon::class);
    });

    it('hashes the password when set', function () {
        $user = User::factory()->create(['password' => 'secret123']);
        expect($user->password)->not->toBe('secret123');
        expect(password_verify('secret123', $user->password))->toBeTrue();
    });
});

describe('User model JWT', function () {
    it('returns the primary key as the JWT identifier', function () {
        $user = User::factory()->create();
        expect($user->getJWTIdentifier())->toBe($user->getKey());
    });

    it('returns an empty array for JWT custom claims', function () {
        $user = User::factory()->make();
        expect($user->getJWTCustomClaims())->toBeArray()->toBeEmpty();
    });
});

describe('User model avatar', function () {
    it('returns an empty string when avatar is null', function () {
        $user = User::factory()->make(['avatar' => null]);
        expect($user->avatar_url)->toBe('');
    });

    it('returns the avatar field value when media is not set', function () {
        $user = User::factory()->make(['avatar' => 'https://example.com/avatar.png']);
        // Without media library, falls back to the avatar field
        expect($user->avatar_url)->toBe('https://example.com/avatar.png');
    });
});

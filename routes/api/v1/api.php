<?php

declare(strict_types=1);

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | API V1 Routes
 |--------------------------------------------------------------------------
 */

Route::prefix('auth')->group(function (): void {
    // Public routes
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('signin/request', [AuthController::class, 'signinRequest'])->name('auth.signin.request');
    Route::post('signin/verify', [AuthController::class, 'signinVerify'])->name('auth.signin.verify');
    Route::post('signup/request', [AuthController::class, 'signupRequest'])->name('auth.signup.request');
    Route::post('signup/verify', [AuthController::class, 'signupVerify'])->name('auth.signup.verify');
    Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('auth.password.forgot');
    Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('auth.password.reset');
    Route::post('code/resend', [AuthController::class, 'resendCode'])->name('auth.code.resend');

    Route::get('{provider}/redirect', [AuthController::class, 'redirectToProvider'])->name('auth.social.redirect');
    Route::get('{provider}/callback', [AuthController::class, 'handleProviderCallback'])->name('auth.social.callback');

    // Authenticated routes
    Route::middleware(['auth:api'])->group(function (): void {
        Route::get('me', [ProfileController::class, 'me'])->name('auth.me');
        Route::post('logout', [ProfileController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [ProfileController::class, 'refresh'])->name('auth.refresh');
        Route::post('profile', [ProfileController::class, 'profileUpdate'])->name('auth.profile.update');
    });
});

Route::middleware(['auth:api'])->prefix('notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
    Route::delete('{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

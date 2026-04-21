<?php

declare(strict_types=1);

use App\Http\Controllers\V1\AuthController;
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
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('profile', [AuthController::class, 'profileUpdate'])->name('auth.profile.update');
    });
});

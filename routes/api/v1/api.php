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

    Route::get('{provider}/redirect', [AuthController::class, 'redirectToProvider'])->name('auth.social.redirect');
    Route::get('{provider}/callback', [AuthController::class, 'handleProviderCallback'])->name('auth.social.callback');

    Route::post('password/request', [AuthController::class, 'requestPasswordReset'])->name('auth.password.request');
    Route::get('password/confirm', [AuthController::class, 'confirmPasswordReset'])->name('auth.password.confirm');

    // Authenticated routes
    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('profile', [AuthController::class, 'profileUpdate'])->name('auth.profile.update');
    });
});

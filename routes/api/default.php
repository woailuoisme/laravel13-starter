<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['message' => 'Welcome to API']))->name('home');

Route::prefix('pay')
    ->name('pay.')
    ->group(function () {
        Route::get('success', fn () => response()->json(['message' => 'Payment successful']))->name('success');

        Route::get('cancel', fn () => response()->json(['message' => 'Payment cancelled']))->name('cancel');
    });

<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Welcome to API']);
})->name('home');

Route::prefix('pay')->name('pay.')->group(function () {
    Route::get('success', function () {
        return response()->json(['message' => 'Payment successful']);
    })->name('success');

    Route::get('cancel', function () {
        return response()->json(['message' => 'Payment cancelled']);
    })->name('cancel');
});

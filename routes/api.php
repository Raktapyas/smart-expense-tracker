<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('user', [AuthController::class, 'me'])->name('user');

        Route::get('categories', CategoryController::class)->name('categories');

        Route::patch('accounts/{account}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
        Route::apiResource('accounts', AccountController::class)->except(['show', 'destroy']);

        Route::apiResource('transactions', TransactionController::class);
    });
});

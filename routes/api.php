<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InsightController;
use App\Http\Controllers\Api\V1\RecurringTransactionController;
use App\Http\Controllers\Api\V1\RuleController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\CategorySuggestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('user', [AuthController::class, 'me'])->name('user');

        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('insights', InsightController::class)->name('insights');

        Route::get('categories', CategoryController::class)->name('categories');
        Route::get('categorise', CategorySuggestionController::class)->name('categorise');

        Route::patch('accounts/{account}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
        Route::apiResource('accounts', AccountController::class)->except(['show', 'destroy']);

        Route::apiResource('transactions', TransactionController::class);

        Route::apiResource('budgets', BudgetController::class)->except(['show']);

        Route::apiResource('rules', RuleController::class)->only(['index', 'store', 'destroy']);

        Route::patch('recurring/{recurring}/toggle', [RecurringTransactionController::class, 'toggle'])->name('recurring.toggle');
        Route::apiResource('recurring', RecurringTransactionController::class)->except(['show']);
    });
});

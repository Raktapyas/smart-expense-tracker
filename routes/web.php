<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryRuleController;
use App\Http\Controllers\CategorySuggestionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::resource('accounts', AccountController::class)->except(['show', 'destroy']);
    Route::patch('/accounts/{account}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
    Route::resource('transactions', TransactionController::class)->except(['show']);
    Route::resource('budgets', BudgetController::class)->except(['show']);
    Route::get('/categorise', CategorySuggestionController::class)
        ->middleware('throttle:60,1')->name('categorise');
    Route::resource('rules', CategoryRuleController::class)->only(['index', 'store', 'destroy']);
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

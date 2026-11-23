<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
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
    Route::view('/dashboard', 'dashboard')->name('dashboard');
});

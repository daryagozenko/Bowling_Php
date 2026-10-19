<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaneController;
use Illuminate\Support\Facades\Route;

// Гость
Route::get('/', fn () => redirect()->route('dashboard'));

// Авторизация (только для гостей)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Только для авторизованных
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware('admin')->group(function () {
    Route::resource('lanes', LaneController::class);
    Route::post('/lanes/{lane}/archive', [LaneController::class, 'archive'])
    ->name('lanes.archive');
});
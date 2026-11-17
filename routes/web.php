<?php

declare(strict_types=1);

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DoctorAuthController;
use App\Http\Controllers\DoctorDashboardController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root Route
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (auth('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }

    if (auth('doctor')->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Doctor Guest Routes (guard: doctor)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:doctor,admin')->group(function (): void {
    Route::get('/register', [DoctorAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [DoctorAuthController::class, 'register'])->middleware('throttle:register');

    Route::get('/login', [DoctorAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [DoctorAuthController::class, 'login'])->middleware('throttle:login');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:google-auth')
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:google-auth')
        ->name('auth.google.callback');
});

/*
|--------------------------------------------------------------------------
| Doctor Authenticated Routes (guard: doctor)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:doctor', 'doctor.active'])->group(function (): void {
    Route::post('/logout', [DoctorAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DoctorDashboardController::class, 'index'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Admin Guest Routes (guard: admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware('guest:doctor,admin')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');
});

/*
|--------------------------------------------------------------------------
| Admin Authenticated Routes (guard: admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware('auth:admin')->group(function (): void {
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
});

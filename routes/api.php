<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function (): void {
    // Public authentication routes
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:api-register')
            ->name('api.v1.auth.register');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:api-login')
            ->name('api.v1.auth.login');

        Route::post('google', [AuthController::class, 'google'])
            ->middleware('throttle:api-google')
            ->name('api.v1.auth.google');
    });

    // Protected authentication routes
    Route::middleware(['auth:sanctum', 'doctor.active'])->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('api.v1.auth.logout-all');
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });
});

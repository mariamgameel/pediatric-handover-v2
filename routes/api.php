<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\DoctorController as AdminDoctorController;
use App\Http\Controllers\Api\V1\Admin\PatientController as AdminPatientController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\ProfileController;
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

    // Protected routes
    Route::middleware(['auth:sanctum', 'doctor.active'])->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('api.v1.auth.logout-all');
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });

        Route::patch('profile', [ProfileController::class, 'update'])
            ->middleware('throttle:api-profile')
            ->name('api.v1.profile.update');

        $patientIdPattern = trim(trim((string) config('hospital.patient_id_pattern', '/^[A-Za-z0-9\-_]+$/'), '/'), '^$');
        $doctorIdPattern = trim(trim((string) config('hospital.doctor_id_pattern', config('hospital.patient_id_pattern', '/^[A-Za-z0-9\-_]+$/')), '/'), '^$');

        // Patients read routes
        Route::middleware('throttle:api-patients-read')->group(function () use ($patientIdPattern): void {
            Route::get('patients', [PatientController::class, 'index'])
                ->name('api.v1.patients.index');
            Route::get('patients/{patient_id}', [PatientController::class, 'show'])
                ->where('patient_id', $patientIdPattern)
                ->name('api.v1.patients.show');
        });

        // Admin routes
        Route::middleware('admin')->prefix('admin')->group(function () use ($patientIdPattern, $doctorIdPattern): void {
            // Patient write routes
            Route::middleware('throttle:api-admin-write')->group(function () use ($patientIdPattern): void {
                Route::post('patients', [AdminPatientController::class, 'store'])
                    ->name('api.v1.admin.patients.store');
                Route::put('patients/{patient_id}', [AdminPatientController::class, 'update'])
                    ->where('patient_id', $patientIdPattern)
                    ->name('api.v1.admin.patients.update');
                Route::delete('patients/{patient_id}', [AdminPatientController::class, 'destroy'])
                    ->where('patient_id', $patientIdPattern)
                    ->name('api.v1.admin.patients.destroy');
            });

            // Doctor read routes
            Route::middleware('throttle:api-admin-read')->group(function () use ($doctorIdPattern): void {
                Route::get('doctors', [AdminDoctorController::class, 'index'])
                    ->name('api.v1.admin.doctors.index');
                Route::get('doctors/{doctor_id}', [AdminDoctorController::class, 'show'])
                    ->where('doctor_id', $doctorIdPattern)
                    ->name('api.v1.admin.doctors.show');
            });

            // Doctor write routes
            Route::middleware('throttle:api-admin-write')->group(function () use ($doctorIdPattern): void {
                Route::delete('doctors/{doctor_id}', [AdminDoctorController::class, 'destroy'])
                    ->where('doctor_id', $doctorIdPattern)
                    ->name('api.v1.admin.doctors.destroy');
                Route::post('doctors/{doctor_id}/restore', [AdminDoctorController::class, 'restore'])
                    ->where('doctor_id', $doctorIdPattern)
                    ->name('api.v1.admin.doctors.restore');
            });
        });
    });
});

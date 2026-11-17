<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DoctorController as AdminDoctorController;
use App\Http\Controllers\Admin\PatientController as AdminPatientController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Doctor\PatientController as DoctorPatientController;
use App\Http\Controllers\DoctorAuthController;
use App\Http\Controllers\DoctorDashboardController;
use App\Http\Controllers\DoctorProfileController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root Route
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
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
Route::middleware('guest:doctor')->group(function (): void {
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

    Route::get('/profile', [DoctorProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [DoctorProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [DoctorProfileController::class, 'update'])->name('profile.update');

    Route::get('/patients', [DoctorPatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/{patient_id}', [DoctorPatientController::class, 'show'])->name('patients.show');
});

/*
|--------------------------------------------------------------------------
| Admin Authenticated Routes (guard: doctor, with admin role)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth:doctor', 'doctor.active', 'admin'])->group(function (): void {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Admin patient management (create placed before wildcard parameter)
    Route::get('/patients', [AdminPatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/create', [AdminPatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [AdminPatientController::class, 'store'])->name('patients.store');
    Route::get('/patients/{patient_id}', [AdminPatientController::class, 'show'])->name('patients.show');
    Route::get('/patients/{patient_id}/edit', [AdminPatientController::class, 'edit'])->name('patients.edit');
    Route::put('/patients/{patient_id}', [AdminPatientController::class, 'update'])->name('patients.update');
    Route::delete('/patients/{patient_id}', [AdminPatientController::class, 'destroy'])->name('patients.destroy');

    // Admin doctor management (static before {param})
    Route::get('/doctors', [AdminDoctorController::class, 'index'])->name('doctors.index');
    Route::get('/doctors/{doctor_id}', [AdminDoctorController::class, 'show'])->name('doctors.show');
    Route::delete('/doctors/{doctor_id}', [AdminDoctorController::class, 'destroy'])->name('doctors.destroy');
    Route::post('/doctors/{doctor_id}/restore', [AdminDoctorController::class, 'restore'])->name('doctors.restore');
});

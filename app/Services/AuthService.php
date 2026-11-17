<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Http\Request;

class AuthService
{
    /**
     * Register a new doctor and log them in.
     *
     * @param array<string, mixed> $data
     */
    public function registerDoctor(array $data, Request $request): Doctor
    {
        $doctor = Doctor::create([
            'doctor_id' => $data['doctor_id'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone_number' => $data['phone_number'] ?? null,
            'last_login_at' => now(),
        ]);

        auth('doctor')->login($doctor);
        $request->session()->regenerate();

        return $doctor;
    }

    /**
     * Authenticate a doctor using email and password.
     */
    public function loginDoctor(string $email, string $password, Request $request): bool
    {
        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        if (!auth('doctor')->attempt($credentials)) {
            return false;
        }

        $doctor = auth('doctor')->user();
        if ($doctor === null || $doctor->trashed()) {
            auth('doctor')->logout();
            return false;
        }

        $doctor->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return true;
    }

    /**
     * Log out the authenticated doctor.
     */
    public function logoutDoctor(Request $request): void
    {
        auth('doctor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Doctor;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Known default passwords prohibited in production environments.
     *
     * @var list<string>
     */
    protected const INSECURE_DEFAULT_PASSWORDS = [
        'password',
        'admin',
        'admin123',
        'adminsecret123',
        'secret',
        '123456',
        '12345678',
        'root',
        'changeme',
        'pediatric',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctorId = config('hospital.admin_doctor_id') !== null
            ? (string) config('hospital.admin_doctor_id')
            : (string) env('ADMIN_DOCTOR_ID', 'DOC-ADMIN-01');

        $name = config('hospital.admin_name') !== null
            ? (string) config('hospital.admin_name')
            : (string) env('ADMIN_NAME', 'Hospital Admin');

        $email = config('hospital.admin_email') !== null
            ? (string) config('hospital.admin_email')
            : (string) env('ADMIN_EMAIL', 'admin@hospital.local');

        $password = config('hospital.admin_password') !== null
            ? (string) config('hospital.admin_password')
            : (string) env('ADMIN_PASSWORD', '');

        $environment = app()->environment();
        $isLocalOrTesting = app()->environment(['local', 'testing']);

        if (!$isLocalOrTesting) {
            if ($password === '') {
                throw new RuntimeException("Admin seeding failed: ADMIN_PASSWORD cannot be empty in {$environment} environment.");
            }

            if (in_array(strtolower($password), self::INSECURE_DEFAULT_PASSWORDS, true)) {
                throw new RuntimeException("Admin seeding failed: ADMIN_PASSWORD cannot be a known default password in {$environment} environment.");
            }

            if (strlen($password) < 12) {
                throw new RuntimeException("Admin seeding failed: ADMIN_PASSWORD must be at least 12 characters in {$environment} environment.");
            }
        }

        // Use fallback password for local and testing environments if unset
        if ($password === '' && $isLocalOrTesting) {
            $password = 'password';
        }

        $byDoctorId = Doctor::withTrashed()->where('doctor_id', $doctorId)->first();
        $byEmail = Doctor::withTrashed()->where('email', $email)->first();

        if ($byDoctorId !== null || $byEmail !== null) {
            // Promote only if BOTH doctor_id and email match the SAME row
            if ($byDoctorId === null || $byEmail === null || $byDoctorId->doctor_id !== $byEmail->doctor_id) {
                $errorMsg = "Admin seeding aborted: Doctor ID '{$doctorId}' and Email '{$email}' match different rows or an existing account conflict exists. Account takeover prevented.";
                $this->command?->error($errorMsg);
                throw new RuntimeException($errorMsg);
            }

            // If a matching soft-deleted doctor exists: restore it, promote it, update its password, and print a notice.
            if ($byDoctorId->trashed()) {
                $byDoctorId->restore();
                $this->command?->info("Restored soft-deleted doctor '{$byDoctorId->full_name}' ({$doctorId}) and promoted to admin.");
            }

            $byDoctorId->update([
                'full_name' => $name,
                'password' => $password,
            ]);
            $byDoctorId->makeAdmin();

            return;
        }

        // Neither exists: create new doctor with is_admin = true
        $doctor = Doctor::create([
            'doctor_id' => $doctorId,
            'full_name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $doctor->makeAdmin();

        $this->command?->info("Created new admin doctor '{$doctor->full_name}' ({$doctorId}).");
    }
}

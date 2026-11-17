<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Doctor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class AdminCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or promote a hospital doctor as system administrator';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $doctorId = (string) $this->ask('Hospital Doctor ID');
        $name = (string) $this->ask('Full Name');
        $email = (string) $this->ask('Email Address');
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm Password');

        $validator = Validator::make([
            'doctor_id' => $doctorId,
            'full_name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'doctor_id' => ['required', 'string', 'max:' . config('hospital.doctor_id_max_length', 64)],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'same:password_confirmation'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $byDoctorId = Doctor::withTrashed()->where('doctor_id', $doctorId)->first();
        $byEmail = Doctor::withTrashed()->where('email', $email)->first();

        if ($byDoctorId !== null || $byEmail !== null) {
            // Promote only if BOTH doctor_id and email match the SAME row
            if ($byDoctorId === null || $byEmail === null || $byDoctorId->doctor_id !== $byEmail->doctor_id) {
                $this->error("Cannot promote doctor: Doctor ID '{$doctorId}' and Email '{$email}' match different rows or an existing account conflict exists. Account takeover prevented.");
                return self::FAILURE;
            }

            // If a matching soft-deleted doctor exists: restore it, promote it, update its password, and print a notice.
            if ($byDoctorId->trashed()) {
                $byDoctorId->restore();
                $byDoctorId->update([
                    'full_name' => $name,
                    'password' => $password,
                ]);
                $byDoctorId->makeAdmin();
                $this->info("Restored soft-deleted doctor '{$byDoctorId->full_name}' ({$doctorId}) and promoted to admin.");
                return self::SUCCESS;
            }

            $byDoctorId->update([
                'full_name' => $name,
                'password' => $password,
            ]);
            $byDoctorId->makeAdmin();
            $this->info("Promoted existing doctor '{$byDoctorId->full_name}' ({$doctorId}) to admin.");
            return self::SUCCESS;
        }

        $doctor = Doctor::create([
            'doctor_id' => $doctorId,
            'full_name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $doctor->makeAdmin();

        $this->info("Admin doctor '{$doctor->full_name}' ({$doctorId}) created successfully.");

        return self::SUCCESS;
    }
}

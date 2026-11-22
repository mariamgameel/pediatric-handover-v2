<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;

class DoctorPolicy
{
    /**
     * Determine whether the doctor can view any doctors.
     */
    public function viewAny(Doctor $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the doctor can view the doctor.
     */
    public function view(Doctor $user, ?Doctor $doctor = null): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the doctor can delete the doctor.
     * An admin cannot delete their own doctor account.
     */
    public function delete(Doctor $user, ?Doctor $doctor = null): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        if ($doctor !== null && $user->doctor_id === $doctor->doctor_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the doctor can restore the doctor.
     * An admin cannot restore their own doctor account.
     */
    public function restore(Doctor $user, ?Doctor $doctor = null): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($doctor !== null && $user->doctor_id === $doctor->doctor_id) {
            return false;
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Doctor;

class DoctorPolicy
{
    /**
     * Determine whether the user can view any doctors.
     */
    public function viewAny(Doctor|Admin $user): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the user can view the doctor.
     */
    public function view(Doctor|Admin $user, ?Doctor $doctor = null): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the user can delete the doctor.
     */
    public function delete(Doctor|Admin $user, ?Doctor $doctor = null): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the user can restore the doctor.
     */
    public function restore(Doctor|Admin $user, ?Doctor $doctor = null): bool
    {
        return $user instanceof Admin;
    }
}

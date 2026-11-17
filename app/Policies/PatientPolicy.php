<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;

class PatientPolicy
{
    /**
     * Determine whether the user can view any patients.
     */
    public function viewAny(Doctor|Admin $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the patient.
     */
    public function view(Doctor|Admin $user, ?Patient $patient = null): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create patients.
     */
    public function create(Doctor|Admin $user): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the user can update the patient.
     */
    public function update(Doctor|Admin $user, ?Patient $patient = null): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the user can delete the patient.
     */
    public function delete(Doctor|Admin $user, ?Patient $patient = null): bool
    {
        return $user instanceof Admin;
    }
}

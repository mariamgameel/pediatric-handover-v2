<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;
use App\Models\Patient;

class PatientPolicy
{
    /**
     * Determine whether the doctor can view any patients.
     */
    public function viewAny(Doctor $user): bool
    {
        return true;
    }

    /**
     * Determine whether the doctor can view the patient.
     */
    public function view(Doctor $user, ?Patient $patient = null): bool
    {
        return true;
    }

    /**
     * Determine whether the doctor can create patients.
     */
    public function create(Doctor $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the doctor can update the patient.
     */
    public function update(Doctor $user, ?Patient $patient = null): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the doctor can delete the patient.
     */
    public function delete(Doctor $user, ?Patient $patient = null): bool
    {
        return $user->isAdmin();
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;

class ClinicalUpdatePolicy
{
    /**
     * Determine whether the doctor can view any clinical updates.
     */
    public function viewAny(Doctor $user): bool
    {
        return true;
    }

    /**
     * Determine whether the doctor can view the clinical update.
     */
    public function view(Doctor $user, ?ClinicalUpdate $update = null): bool
    {
        return true;
    }

    /**
     * Determine whether the doctor can create clinical updates.
     */
    public function create(Doctor $user): bool
    {
        return true;
    }

    /**
     * Determine whether the doctor can correct the clinical update.
     * Only the original author or an admin can correct an update.
     */
    public function correct(Doctor $user, ClinicalUpdate $original): bool
    {
        return $user->doctor_id === $original->doctor_id || $user->isAdmin();
    }

    /**
     * Determine whether the doctor can update the clinical update.
     * Clinical updates are strictly immutable; always returns false.
     */
    public function update(Doctor $user, ?ClinicalUpdate $update = null): bool
    {
        return false;
    }

    /**
     * Determine whether the doctor can delete the clinical update.
     * Clinical updates are strictly immutable; always returns false.
     */
    public function delete(Doctor $user, ?ClinicalUpdate $update = null): bool
    {
        return false;
    }
}

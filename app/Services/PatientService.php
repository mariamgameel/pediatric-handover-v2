<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CannotDeletePatientWithUpdatesException;
use App\Models\Patient;

class PatientService
{
    /**
     * Delete a patient record, preventing deletion if clinical updates exist.
     *
     * @throws CannotDeletePatientWithUpdatesException
     */
    public function delete(Patient $patient): void
    {
        if ($patient->clinicalUpdates()->exists()) {
            throw new CannotDeletePatientWithUpdatesException();
        }

        $patient->delete();
    }
}

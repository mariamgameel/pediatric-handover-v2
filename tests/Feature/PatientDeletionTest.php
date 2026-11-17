<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\CannotDeletePatientWithUpdatesException;
use App\Models\ClinicalUpdate;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_without_updates_can_be_deleted_via_service(): void
    {
        $patient = Patient::factory()->create();
        $patientId = $patient->patient_id;

        $service = app(PatientService::class);
        $service->delete($patient);

        $this->assertDatabaseMissing('patients', ['patient_id' => $patientId]);
    }

    public function test_patient_with_updates_cannot_be_deleted_via_service(): void
    {
        $patient = Patient::factory()->create();
        ClinicalUpdate::factory()->create(['patient_id' => $patient->patient_id]);

        $service = app(PatientService::class);

        $this->expectException(CannotDeletePatientWithUpdatesException::class);
        $this->expectExceptionMessage('Cannot delete patient because clinical updates exist');

        $service->delete($patient);
    }

    public function test_patient_with_updates_cannot_be_deleted_at_database_level_due_to_fk_restrict(): void
    {
        $patient = Patient::factory()->create();
        ClinicalUpdate::factory()->create(['patient_id' => $patient->patient_id]);

        $this->expectException(QueryException::class);

        $patient->delete();
    }
}

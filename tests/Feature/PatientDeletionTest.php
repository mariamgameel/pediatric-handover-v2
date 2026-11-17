<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\CannotDeletePatientWithUpdatesException;
use App\Models\ClinicalUpdate;
use App\Models\Doctor;
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

    public function test_delete_converts_database_foreign_key_query_exception_to_cannot_delete_exception(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        // Simulate a race condition: a clinical update is created AFTER the service's
        // clinicalUpdates()->exists() check passes, right before the DELETE query executes.
        Patient::deleting(function (Patient $p) use ($doctor): void {
            ClinicalUpdate::factory()->create([
                'patient_id' => $p->patient_id,
                'doctor_id' => $doctor->doctor_id,
            ]);
        });

        $service = app(PatientService::class);

        try {
            $service->delete($patient);
            $this->fail('Expected CannotDeletePatientWithUpdatesException was not thrown.');
        } catch (CannotDeletePatientWithUpdatesException $e) {
            $this->assertInstanceOf(QueryException::class, $e->getPrevious());
            $this->assertDatabaseHas('patients', ['patient_id' => $patient->patient_id]);
        }
    }

    public function test_non_foreign_key_query_exception_is_rethrown(): void
    {
        $patient = Patient::factory()->create();

        Patient::deleting(function (): void {
            throw new QueryException(
                'sqlite',
                'DELETE FROM patients WHERE 1 = 1',
                [],
                new \PDOException('General error: 1 no such table: non_existent', 42000)
            );
        });

        $service = app(PatientService::class);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('no such table');

        $service->delete($patient);
    }
}

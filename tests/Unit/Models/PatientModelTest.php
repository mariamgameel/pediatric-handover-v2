<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PatientModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_has_string_non_incrementing_primary_key(): void
    {
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-FIXED-01',
        ]);

        $this->assertSame('PAT-FIXED-01', $patient->patient_id);
        $this->assertFalse($patient->getIncrementing());
        $this->assertSame('string', $patient->getKeyType());
    }

    public function test_patient_id_is_immutable_after_creation(): void
    {
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-IMMUTABLE-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('patient_id is immutable');

        $patient->update(['patient_id' => 'PAT-CHANGED-02']);
    }

    public function test_patient_has_many_clinical_updates(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        ClinicalUpdate::factory()->count(2)->create([
            'patient_id' => $patient->patient_id,
            'doctor_id' => $doctor->doctor_id,
        ]);

        $this->assertCount(2, $patient->clinicalUpdates);
    }
}

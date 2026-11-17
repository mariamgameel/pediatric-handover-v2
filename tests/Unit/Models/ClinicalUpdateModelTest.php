<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ClinicalUpdateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_update_generates_ulid_primary_key(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $update = ClinicalUpdate::create([
            'patient_id' => $patient->patient_id,
            'doctor_id' => $doctor->doctor_id,
            'content' => 'Initial checkup notes.',
        ]);

        $this->assertNotEmpty($update->update_id);
        $this->assertSame(26, strlen($update->update_id));
        $this->assertFalse($update->getIncrementing());
    }

    public function test_clinical_update_cannot_be_deleted(): void
    {
        $update = ClinicalUpdate::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Clinical updates are retained permanently');

        $update->delete();
    }

    public function test_clinical_update_relationships_and_corrections_chain(): void
    {
        $patient = Patient::factory()->create();
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();

        $original = ClinicalUpdate::factory()->create([
            'patient_id' => $patient->patient_id,
            'doctor_id' => $doctor1->doctor_id,
            'content' => 'First note.',
        ]);

        $correction = ClinicalUpdate::factory()->correction($original)->create([
            'doctor_id' => $doctor2->doctor_id,
        ]);

        $this->assertTrue($original->patient->is($patient));
        $this->assertTrue($original->doctor->is($doctor1));

        $this->assertNull($original->original);
        $this->assertCount(1, $original->corrections);
        $this->assertTrue($original->corrections->first()->is($correction));

        $this->assertNotNull($correction->original);
        $this->assertTrue($correction->original->is($original));
    }

    public function test_clinical_update_resolves_author_even_when_doctor_is_soft_deleted(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $update = ClinicalUpdate::factory()->create([
            'doctor_id' => $doctor->doctor_id,
            'patient_id' => $patient->patient_id,
        ]);

        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);

        $freshUpdate = ClinicalUpdate::find($update->update_id);
        $this->assertNotNull($freshUpdate);
        $this->assertNotNull($freshUpdate->doctor);
        $this->assertSame($doctor->doctor_id, $freshUpdate->doctor->doctor_id);
        $this->assertTrue($freshUpdate->doctor->trashed());
    }
}

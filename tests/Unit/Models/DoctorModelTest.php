<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DoctorModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_has_string_non_incrementing_primary_key(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-SPECIFIC-01',
        ]);

        $this->assertSame('DOC-SPECIFIC-01', $doctor->doctor_id);
        $this->assertFalse($doctor->getIncrementing());
        $this->assertSame('string', $doctor->getKeyType());
    }

    public function test_doctor_id_is_immutable_after_creation(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-FIXED-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('doctor_id is immutable');

        $doctor->update(['doctor_id' => 'DOC-MUTATED-02']);
    }

    public function test_doctor_can_soft_delete(): void
    {
        $doctor = Doctor::factory()->create();
        $doctorId = $doctor->doctor_id;

        $doctor->delete();

        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctorId]);
        $this->assertNotNull(Doctor::withTrashed()->find($doctorId));
        $this->assertNull(Doctor::find($doctorId));
    }

    public function test_doctor_has_many_clinical_updates(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        ClinicalUpdate::factory()->create([
            'doctor_id' => $doctor->doctor_id,
            'patient_id' => $patient->patient_id,
        ]);

        $this->assertCount(1, $doctor->clinicalUpdates);
        $this->assertInstanceOf(ClinicalUpdate::class, $doctor->clinicalUpdates->first());
    }

    public function test_doctor_display_name_accessor(): void
    {
        $doctor = Doctor::factory()->create([
            'full_name' => 'Dr. Gregory House',
        ]);

        $this->assertSame('Dr. Gregory House', $doctor->display_name);

        $doctor->delete();

        $this->assertSame('Dr. Gregory House (deleted)', $doctor->display_name);
    }

    public function test_mass_assignment_of_is_admin_is_silently_ignored(): void
    {
        // 1. Doctor::create
        $doctor = Doctor::create([
            'doctor_id' => 'DOC-MASS-01',
            'full_name' => 'Dr. Mass Assignment',
            'email' => 'mass@hospital.local',
            'password' => 'Password123!',
            'is_admin' => true,
        ]);

        $this->assertFalse($doctor->isAdmin());
        $this->assertFalse((bool) $doctor->fresh()->is_admin);

        // 2. Doctor::fill
        $doctor->fill(['is_admin' => true]);
        $doctor->save();

        $this->assertFalse($doctor->isAdmin());
        $this->assertFalse((bool) $doctor->fresh()->is_admin);

        // 3. Doctor::update
        $doctor->update(['is_admin' => true]);

        $this->assertFalse($doctor->isAdmin());
        $this->assertFalse((bool) $doctor->fresh()->is_admin);
    }
}

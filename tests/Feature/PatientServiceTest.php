<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\CannotDeletePatientWithUpdatesException;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PatientServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PatientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PatientService();
    }

    public function test_paginate_patients_with_20_per_page(): void
    {
        Patient::factory()->count(25)->create();

        $paginator = $this->service->paginate([]);

        $this->assertSame(20, $paginator->perPage());
        $this->assertSame(25, $paginator->total());
        $this->assertCount(20, $paginator->items());
    }

    public function test_paginate_patients_searches_by_id_and_english_and_arabic_name(): void
    {
        Patient::factory()->create([
            'patient_id' => 'PAT-SRCH-999',
            'full_name' => 'John Constantine',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-AR-001',
            'full_name' => 'يوسف محمد عبد الرحمن',
        ]);

        // Search by ID
        $byId = $this->service->paginate(['search' => 'SRCH-999']);
        $this->assertSame(1, $byId->total());
        $this->assertSame('John Constantine', $byId->first()->full_name);

        // Search by English Name
        $byName = $this->service->paginate(['search' => 'Constantine']);
        $this->assertSame(1, $byName->total());
        $this->assertSame('John Constantine', $byName->first()->full_name);

        // Search by Arabic Name
        $byArabic = $this->service->paginate(['search' => 'يوسف']);
        $this->assertSame(1, $byArabic->total());
        $this->assertSame('يوسف محمد عبد الرحمن', $byArabic->first()->full_name);

        // Search with escaped wildcards
        $byWildcards = $this->service->paginate(['search' => '%_']);
        $this->assertSame(0, $byWildcards->total());
    }

    public function test_paginate_patients_filters_by_gender(): void
    {
        Patient::factory()->create(['full_name' => 'Male Patient', 'gender' => 'male']);
        Patient::factory()->create(['full_name' => 'Female Patient', 'gender' => 'female']);

        $males = $this->service->paginate(['gender' => 'male']);
        $this->assertSame(1, $males->total());
        $this->assertSame('Male Patient', $males->first()->full_name);

        $females = $this->service->paginate(['gender' => 'female']);
        $this->assertSame(1, $females->total());
        $this->assertSame('Female Patient', $females->first()->full_name);
    }

    public function test_paginate_patients_sorts_by_name_direction(): void
    {
        Patient::factory()->create(['full_name' => 'Aaron Smith']);
        Patient::factory()->create(['full_name' => 'Zachary Taylor']);

        $asc = $this->service->paginate(['direction' => 'asc']);
        $this->assertSame('Aaron Smith', $asc->first()->full_name);

        $desc = $this->service->paginate(['direction' => 'desc']);
        $this->assertSame('Zachary Taylor', $desc->first()->full_name);
    }

    public function test_create_and_update_patient(): void
    {
        $patient = $this->service->create([
            'patient_id' => 'PAT-NEW-01',
            'full_name' => 'Baby Girl Jane',
            'date_of_birth' => '2023-01-15',
            'gender' => 'female',
        ]);

        $this->assertSame('PAT-NEW-01', $patient->patient_id);
        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-NEW-01',
            'full_name' => 'Baby Girl Jane',
            'gender' => 'female',
        ]);

        $updated = $this->service->update($patient, [
            'full_name' => 'Jane Doe Updated',
            'date_of_birth' => '2023-01-16',
            'gender' => 'female',
        ]);

        $this->assertSame('Jane Doe Updated', $updated->full_name);
        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-NEW-01',
            'full_name' => 'Jane Doe Updated',
        ]);
    }

    public function test_delete_patient_removes_patient_without_updates(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-DEL-FREE']);

        $this->service->delete($patient);

        $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-DEL-FREE']);
    }

    public function test_delete_patient_throws_exception_if_updates_exist(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create(['patient_id' => 'PAT-WITH-UPD']);

        ClinicalUpdate::factory()->create([
            'doctor_id' => $doctor->doctor_id,
            'patient_id' => $patient->patient_id,
        ]);

        $this->expectException(CannotDeletePatientWithUpdatesException::class);

        $this->service->delete($patient);
    }

    public function test_store_patient_request_rules(): void
    {
        $request = new StorePatientRequest();
        $rules = $request->rules();

        $validData = [
            'patient_id' => 'PAT-VAL-01',
            'full_name' => 'Dr. Child',
            'date_of_birth' => '2020-05-10',
            'gender' => 'male',
        ];

        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails());

        // Fails with duplicate patient_id
        Patient::factory()->create(['patient_id' => 'PAT-VAL-01']);
        $validatorDuplicate = Validator::make($validData, $rules);
        $this->assertTrue($validatorDuplicate->fails());
        $this->assertArrayHasKey('patient_id', $validatorDuplicate->errors()->toArray());
    }

    public function test_update_patient_request_rules(): void
    {
        $request = new UpdatePatientRequest();
        $rules = $request->rules();

        $validData = [
            'full_name' => 'Updated Child',
            'date_of_birth' => '2021-02-14',
            'gender' => 'female',
        ];

        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails());

        // Fails if gender is invalid
        $invalidGender = Validator::make(array_merge($validData, ['gender' => 'other']), $rules);
        $this->assertTrue($invalidGender->fails());
        $this->assertArrayHasKey('gender', $invalidGender->errors()->toArray());
    }
}

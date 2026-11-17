<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorPatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_paginated_patient_list(): void
    {
        $doctor = Doctor::factory()->create();
        Patient::factory()->count(25)->create();

        $response = $this->actingAs($doctor, 'doctor')->get('/patients');

        $response->assertOk();
        $response->assertViewHas('patients');
        $this->assertCount(20, $response->viewData('patients'));
    }

    public function test_doctor_can_search_patients_by_id_and_english_name(): void
    {
        $doctor = Doctor::factory()->create();

        Patient::factory()->create([
            'patient_id' => 'PAT-SRCH-999',
            'full_name' => 'John Constantine',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-OTHER-111',
            'full_name' => 'Bruce Wayne',
        ]);

        // Search by ID
        $responseById = $this->actingAs($doctor, 'doctor')->get('/patients?search=SRCH-999');
        $responseById->assertOk();
        $responseById->assertSee('John Constantine');
        $responseById->assertDontSee('Bruce Wayne');

        // Search by Name
        $responseByName = $this->actingAs($doctor, 'doctor')->get('/patients?search=Constantine');
        $responseByName->assertOk();
        $responseByName->assertSee('John Constantine');
        $responseByName->assertDontSee('Bruce Wayne');
    }

    public function test_doctor_can_search_patients_with_arabic_name(): void
    {
        $doctor = Doctor::factory()->create();

        Patient::factory()->create([
            'patient_id' => 'PAT-AR-001',
            'full_name' => 'يوسف محمد عبد الرحمن',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-EN-002',
            'full_name' => 'David Miller',
        ]);

        $response = $this->actingAs($doctor, 'doctor')->get('/patients?search=' . urlencode('يوسف'));

        $response->assertOk();
        $response->assertSee('يوسف محمد عبد الرحمن');
        $response->assertDontSee('David Miller');
    }

    public function test_doctor_can_filter_patients_by_gender(): void
    {
        $doctor = Doctor::factory()->create();

        Patient::factory()->create(['full_name' => 'Male Patient', 'gender' => 'male']);
        Patient::factory()->create(['full_name' => 'Female Patient', 'gender' => 'female']);

        $response = $this->actingAs($doctor, 'doctor')->get('/patients?gender=male');

        $response->assertOk();
        $response->assertSee('Male Patient');
        $response->assertDontSee('Female Patient');
    }

    public function test_doctor_can_sort_patients_by_name_direction(): void
    {
        $doctor = Doctor::factory()->create();

        Patient::factory()->create(['full_name' => 'Aaron Smith']);
        Patient::factory()->create(['full_name' => 'Zachary Taylor']);

        $responseAsc = $this->actingAs($doctor, 'doctor')->get('/patients?direction=asc');
        $responseAsc->assertOk();
        $patientsAsc = $responseAsc->viewData('patients');
        $this->assertSame('Aaron Smith', $patientsAsc->first()->full_name);

        $responseDesc = $this->actingAs($doctor, 'doctor')->get('/patients?direction=desc');
        $responseDesc->assertOk();
        $patientsDesc = $responseDesc->viewData('patients');
        $this->assertSame('Zachary Taylor', $patientsDesc->first()->full_name);
    }

    public function test_doctor_can_view_patient_details(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-SHOW-01',
            'full_name' => 'Alice Cooper',
            'date_of_birth' => '2019-06-15',
            'gender' => 'female',
        ]);

        $response = $this->actingAs($doctor, 'doctor')->get("/patients/{$patient->patient_id}");

        $response->assertOk();
        $response->assertSee('Alice Cooper');
        $response->assertSee('PAT-SHOW-01');
        $response->assertSee('2019-06-15');
        $response->assertSee('Female');
        $response->assertSee('Clinical Updates');
    }

    public function test_doctor_receives_404_for_unknown_patient_id(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($doctor, 'doctor')->get('/patients/PAT-NON-EXISTENT-ID');

        $response->assertNotFound();
    }

    public function test_doctor_cannot_access_admin_patient_routes(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($doctor, 'doctor')->get('/admin/patients')->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->get('/admin/patients/create')->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->post('/admin/patients', [])->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->get("/admin/patients/{$patient->patient_id}")->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->get("/admin/patients/{$patient->patient_id}/edit")->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->put("/admin/patients/{$patient->patient_id}", [])->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->delete("/admin/patients/{$patient->patient_id}")->assertRedirect('/admin/login');
    }

    public function test_doctor_has_no_create_edit_or_delete_routes_for_patients(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        // Standard doctor routes do not have POST/PUT/DELETE (Method Not Allowed)
        $this->actingAs($doctor, 'doctor')->post('/patients', [])->assertStatus(405);
        $this->actingAs($doctor, 'doctor')->put("/patients/{$patient->patient_id}", [])->assertStatus(405);
        $this->actingAs($doctor, 'doctor')->delete("/patients/{$patient->patient_id}")->assertStatus(405);
    }

    public function test_search_with_literal_percent_and_underscore_searches_literally(): void
    {
        $doctor = Doctor::factory()->create();

        Patient::factory()->create([
            'patient_id' => 'PAT-PCT-100',
            'full_name' => 'Patient 100% Stable',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-UND-200',
            'full_name' => 'Patient John_Doe',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-NRM-300',
            'full_name' => 'Patient Normal Name',
        ]);

        // Search for literal '%'
        $responsePercent = $this->actingAs($doctor, 'doctor')->get('/patients?search=' . urlencode('%'));
        $responsePercent->assertOk();
        $responsePercent->assertSee('Patient 100% Stable');
        $responsePercent->assertDontSee('Patient John_Doe');
        $responsePercent->assertDontSee('Patient Normal Name');

        // Search for literal '_'
        $responseUnderscore = $this->actingAs($doctor, 'doctor')->get('/patients?search=' . urlencode('_'));
        $responseUnderscore->assertOk();
        $responseUnderscore->assertDontSee('Patient 100% Stable');
        $responseUnderscore->assertSee('Patient John_Doe');
        $responseUnderscore->assertDontSee('Patient Normal Name');
    }

    public function test_doctor_calling_all_seven_admin_patient_endpoints_is_denied_while_admin_is_allowed(): void
    {
        $doctor = Doctor::factory()->create();
        $admin = Admin::factory()->create();
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-SEVEN-01',
        ]);

        $storePayload = [
            'patient_id' => 'PAT-SEVEN-NEW',
            'full_name' => 'Seven Test Patient',
            'date_of_birth' => '2020-01-01',
            'gender' => 'male',
        ];
        $updatePayload = [
            'full_name' => 'Updated By Admin',
            'date_of_birth' => '2020-01-02',
            'gender' => 'female',
        ];
        $deletePayload = ['confirm_delete' => '1'];

        // --- Doctor under 'doctor' guard is denied on all 7 admin patient endpoints ---
        $this->actingAs($doctor, 'doctor')->get('/admin/patients')->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->get('/admin/patients/create')->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->post('/admin/patients', $storePayload)->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->get("/admin/patients/{$patient->patient_id}")->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->get("/admin/patients/{$patient->patient_id}/edit")->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->put("/admin/patients/{$patient->patient_id}", $updatePayload)->assertRedirect('/admin/login');
        $this->actingAs($doctor, 'doctor')->delete("/admin/patients/{$patient->patient_id}", $deletePayload)->assertRedirect('/admin/login');

        // --- Admin under 'admin' guard is allowed on all 7 endpoints ---
        $this->actingAs($admin, 'admin')->get('/admin/patients')->assertOk();
        $this->actingAs($admin, 'admin')->get('/admin/patients/create')->assertOk();
        $this->actingAs($admin, 'admin')->post('/admin/patients', $storePayload)->assertRedirect('/admin/patients/PAT-SEVEN-NEW');
        $this->actingAs($admin, 'admin')->get("/admin/patients/{$patient->patient_id}")->assertOk();
        $this->actingAs($admin, 'admin')->get("/admin/patients/{$patient->patient_id}/edit")->assertOk();
        $this->actingAs($admin, 'admin')->put("/admin/patients/{$patient->patient_id}", $updatePayload)->assertRedirect("/admin/patients/{$patient->patient_id}");
        $this->actingAs($admin, 'admin')->delete("/admin/patients/{$patient->patient_id}", $deletePayload)->assertRedirect('/admin/patients');
    }
}

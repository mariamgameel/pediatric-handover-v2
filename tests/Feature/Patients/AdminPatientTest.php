<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminPatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_access_redirects_to_login(): void
    {
        $this->get('/admin/patients')->assertRedirect('/login');
        $this->get('/admin/patients/create')->assertRedirect('/login');
        $this->post('/admin/patients', [])->assertRedirect('/login');
    }

    public function test_admin_can_view_patient_list_and_search(): void
    {
        $admin = Doctor::factory()->admin()->create();

        Patient::factory()->create([
            'patient_id' => 'PAT-ADMIN-01',
            'full_name' => 'Michael Corleone',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-ADMIN-02',
            'full_name' => 'Santino Corleone',
        ]);

        $response = $this->actingAs($admin, 'doctor')->get('/admin/patients?search=Michael');

        $response->assertOk();
        $response->assertSee('Michael Corleone');
        $response->assertDontSee('Santino Corleone');
    }

    public function test_admin_can_create_patient_with_valid_data(): void
    {
        $admin = Doctor::factory()->admin()->create();

        $response = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
            'patient_id' => 'PAT-NEW-123',
            'full_name' => 'Tommy Vercetti',
            'date_of_birth' => '2018-05-12',
            'gender' => 'male',
        ]);

        $response->assertRedirect('/admin/patients/PAT-NEW-123');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-NEW-123',
            'full_name' => 'Tommy Vercetti',
            'gender' => 'male',
        ]);
    }

    public function test_create_fails_on_duplicate_patient_id(): void
    {
        $admin = Doctor::factory()->admin()->create();

        Patient::factory()->create([
            'patient_id' => 'PAT-DUP-ID',
        ]);

        $response = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
            'patient_id' => 'PAT-DUP-ID',
            'full_name' => 'Another Patient',
            'date_of_birth' => '2019-01-01',
            'gender' => 'female',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('patient_id');
    }

    public function test_create_fails_on_invalid_gender(): void
    {
        $admin = Doctor::factory()->admin()->create();

        $response = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
            'patient_id' => 'PAT-GENDER-01',
            'full_name' => 'Invalid Gender Patient',
            'date_of_birth' => '2020-01-01',
            'gender' => 'alien_gender',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('gender');
    }

    public function test_create_fails_on_future_date_of_birth(): void
    {
        $admin = Doctor::factory()->admin()->create();

        $response = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
            'patient_id' => 'PAT-FUTURE-01',
            'full_name' => 'Time Traveler',
            'date_of_birth' => now()->addDays(5)->format('Y-m-d'),
            'gender' => 'male',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('date_of_birth');
    }

    public function test_create_fails_on_date_of_birth_before_1900(): void
    {
        $admin = Doctor::factory()->admin()->create();

        $response = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
            'patient_id' => 'PAT-ANCIENT-01',
            'full_name' => 'Ancient Patient',
            'date_of_birth' => '1899-12-31',
            'gender' => 'male',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('date_of_birth');
    }

    public function test_create_fails_on_invalid_patient_id_pattern(): void
    {
        $admin = Doctor::factory()->admin()->create();

        $response = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
            'patient_id' => 'PAT!@#$INVALID',
            'full_name' => 'Bad Pattern',
            'date_of_birth' => '2021-01-01',
            'gender' => 'female',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('patient_id');
    }

    public function test_admin_can_edit_valid_patient_fields(): void
    {
        $admin = Doctor::factory()->admin()->create();
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-EDIT-01',
            'full_name' => 'Old Patient Name',
            'date_of_birth' => '2019-03-10',
            'gender' => 'male',
        ]);

        $response = $this->actingAs($admin, 'doctor')->put("/admin/patients/{$patient->patient_id}", [
            'full_name' => 'New Patient Name',
            'date_of_birth' => '2019-04-15',
            'gender' => 'female',
        ]);

        $response->assertRedirect("/admin/patients/{$patient->patient_id}");
        $response->assertSessionHas('success');

        $patient->refresh();
        $this->assertSame('New Patient Name', $patient->full_name);
        $this->assertSame('2019-04-15', $patient->date_of_birth->format('Y-m-d'));
        $this->assertSame('female', $patient->gender);
    }

    public function test_edit_with_patient_id_in_payload_gives_422_and_leaves_id_unchanged(): void
    {
        $admin = Doctor::factory()->admin()->create();
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-IMMUTABLE-ORIGINAL',
            'full_name' => 'Fixed Patient',
        ]);

        $response = $this->actingAs($admin, 'doctor')->put("/admin/patients/{$patient->patient_id}", [
            'patient_id' => 'PAT-MODIFIED-ATTEMPT',
            'full_name' => 'Attempted Name Change',
            'date_of_birth' => '2020-01-01',
            'gender' => 'male',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('patient_id');

        $patient->refresh();
        $this->assertSame('PAT-IMMUTABLE-ORIGINAL', $patient->patient_id);
        $this->assertSame('Fixed Patient', $patient->full_name);
    }

    public function test_delete_patient_without_updates_succeeds_with_confirmation(): void
    {
        $admin = Doctor::factory()->admin()->create();
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-CLEAN-DELETE',
        ]);

        $response = $this->actingAs($admin, 'doctor')->delete("/admin/patients/{$patient->patient_id}", [
            'confirm_delete' => '1',
        ]);

        $response->assertRedirect('/admin/patients');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-CLEAN-DELETE']);
    }

    public function test_delete_patient_without_confirmation_checkbox_fails(): void
    {
        $admin = Doctor::factory()->admin()->create();
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-UNCONFIRMED',
        ]);

        $response = $this->actingAs($admin, 'doctor')->delete("/admin/patients/{$patient->patient_id}", []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('confirm_delete');

        $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-UNCONFIRMED']);
    }

    public function test_delete_patient_with_clinical_updates_is_blocked_and_patient_remains(): void
    {
        $admin = Doctor::factory()->admin()->create();
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-HAS-UPDATES',
        ]);

        ClinicalUpdate::factory()->create([
            'patient_id' => $patient->patient_id,
            'doctor_id' => $doctor->doctor_id,
        ]);

        $response = $this->actingAs($admin, 'doctor')
            ->from("/admin/patients/{$patient->patient_id}")
            ->delete("/admin/patients/{$patient->patient_id}", [
                'confirm_delete' => '1',
            ]);

        $response->assertRedirect("/admin/patients/{$patient->patient_id}");
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-HAS-UPDATES']);
    }

    public function test_date_of_birth_equal_to_today_in_hospital_timezone_is_accepted_and_tomorrow_is_rejected(): void
    {
        // 2026-11-17 22:30:00 UTC is 2026-11-18 00:30:00 in Africa/Cairo (UTC+2)
        Carbon::setTestNow(Carbon::parse('2026-11-17 22:30:00', 'UTC'));

        $admin = Doctor::factory()->admin()->create();

        try {
            // Cairo today (2026-11-18) must be accepted even though UTC is still 2026-11-17
            $responseToday = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
                'patient_id' => 'PAT-CAIRO-TODAY-01',
                'full_name' => 'Cairo Today Baby',
                'date_of_birth' => '2026-11-18',
                'gender' => 'male',
            ]);

            $responseToday->assertRedirect('/admin/patients/PAT-CAIRO-TODAY-01');
            $responseToday->assertSessionHas('success');
            $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-CAIRO-TODAY-01']);

            // Cairo tomorrow (2026-11-19) must be rejected
            $responseTomorrow = $this->actingAs($admin, 'doctor')->post('/admin/patients', [
                'patient_id' => 'PAT-CAIRO-FUT-02',
                'full_name' => 'Cairo Future Baby',
                'date_of_birth' => '2026-11-19',
                'gender' => 'female',
            ]);

            $responseTomorrow->assertStatus(302);
            $responseTomorrow->assertSessionHasErrors('date_of_birth');
            $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-CAIRO-FUT-02']);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_admin_search_with_literal_percent_and_underscore_searches_literally(): void
    {
        $admin = Doctor::factory()->admin()->create();

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
        $responsePercent = $this->actingAs($admin, 'doctor')->get('/admin/patients?search=' . urlencode('%'));
        $responsePercent->assertOk();
        $responsePercent->assertSee('Patient 100% Stable');
        $responsePercent->assertDontSee('Patient John_Doe');
        $responsePercent->assertDontSee('Patient Normal Name');

        // Search for literal '_'
        $responseUnderscore = $this->actingAs($admin, 'doctor')->get('/admin/patients?search=' . urlencode('_'));
        $responseUnderscore->assertOk();
        $responseUnderscore->assertDontSee('Patient 100% Stable');
        $responseUnderscore->assertSee('Patient John_Doe');
        $responseUnderscore->assertDontSee('Patient Normal Name');
    }
}

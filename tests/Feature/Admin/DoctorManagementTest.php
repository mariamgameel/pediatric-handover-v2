<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Doctor $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Doctor::factory()->admin()->create();
    }

    public function test_admin_can_list_doctors_with_pagination(): void
    {
        Doctor::factory()->count(25)->create();

        $response = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors');

        $response->assertOk();
        $response->assertViewIs('admin.doctors.index');
        $response->assertViewHas('doctors');

        /** @var \Illuminate\Pagination\LengthAwarePaginator $doctors */
        $doctors = $response->viewData('doctors');
        $this->assertSame(20, $doctors->perPage());
        $this->assertSame(26, $doctors->total());
    }

    public function test_admin_can_search_doctors_by_id_name_and_email_including_arabic_name(): void
    {
        Doctor::factory()->create([
            'doctor_id' => 'DOC-ARABIC-01',
            'full_name' => 'د. أحمد محمود',
            'email' => 'ahmed@hospital.local',
        ]);

        Doctor::factory()->create([
            'doctor_id' => 'DOC-ENG-02',
            'full_name' => 'Dr. Gregory House',
            'email' => 'house@hospital.local',
        ]);

        // Search by Arabic name
        $responseArabic = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors?search=أحمد');
        $responseArabic->assertOk();
        $responseArabic->assertSee('DOC-ARABIC-01');
        $responseArabic->assertDontSee('DOC-ENG-02');

        // Search by Doctor ID
        $responseId = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors?search=DOC-ENG-02');
        $responseId->assertOk();
        $responseId->assertSee('Dr. Gregory House');
        $responseId->assertDontSee('د. أحمد محمود');

        // Search by Email
        $responseEmail = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors?search=ahmed@hospital.local');
        $responseEmail->assertOk();
        $responseEmail->assertSee('DOC-ARABIC-01');
        $responseEmail->assertDontSee('DOC-ENG-02');

        // Search with LIKE wildcards escaped
        $responseEscaped = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors?search=%_');
        $responseEscaped->assertOk();
        $responseEscaped->assertDontSee('DOC-ARABIC-01');
        $responseEscaped->assertDontSee('DOC-ENG-02');
    }

    public function test_admin_can_filter_doctors_by_status(): void
    {
        $activeDoc = Doctor::factory()->create([
            'doctor_id' => 'DOC-ACTIVE-01',
            'full_name' => 'Active Doctor',
        ]);

        $deletedDoc = Doctor::factory()->create([
            'doctor_id' => 'DOC-DELETED-02',
            'full_name' => 'Deleted Doctor',
        ]);
        $deletedDoc->delete();

        // Default: active only
        $responseDefault = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors');
        $responseDefault->assertOk();
        $responseDefault->assertSee('DOC-ACTIVE-01');
        $responseDefault->assertDontSee('DOC-DELETED-02');

        // Filter: active
        $responseActive = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors?status=active');
        $responseActive->assertOk();
        $responseActive->assertSee('DOC-ACTIVE-01');
        $responseActive->assertDontSee('DOC-DELETED-02');

        // Filter: deleted
        $responseDeleted = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors?status=deleted');
        $responseDeleted->assertOk();
        $responseDeleted->assertDontSee('DOC-ACTIVE-01');
        $responseDeleted->assertSee('DOC-DELETED-02');

        // Filter: all
        $responseAll = $this->actingAs($this->admin, 'doctor')->get('/admin/doctors?status=all');
        $responseAll->assertOk();
        $responseAll->assertSee('DOC-ACTIVE-01');
        $responseAll->assertSee('DOC-DELETED-02');
    }

    public function test_admin_views_doctor_details_and_authored_clinical_updates(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-VIEW-01',
            'full_name' => 'Dr. Sarah Connor',
            'email' => 'sarah@hospital.local',
            'phone_number' => '+1234567890',
            'google_id' => 'google-secret-id-12345',
            'password' => 'secret_hashed_password',
        ]);

        $patient = Patient::factory()->create(['patient_id' => 'PAT-VIEW-01']);

        ClinicalUpdate::factory()->create([
            'doctor_id' => $doctor->doctor_id,
            'patient_id' => $patient->patient_id,
            'content' => 'Patient has stabilized following pediatric resuscitation protocol.',
        ]);

        $response = $this->actingAs($this->admin, 'doctor')->get("/admin/doctors/{$doctor->doctor_id}");

        $response->assertOk();
        $response->assertViewIs('admin.doctors.show');
        $response->assertSee('DOC-VIEW-01');
        $response->assertSee('Dr. Sarah Connor');
        $response->assertSee('sarah@hospital.local');
        $response->assertSee('+1234567890');
        $response->assertSee('PAT-VIEW-01');
        $response->assertSee('Patient has stabilized');

        // Verify google_id and password hashes are NEVER exposed
        $response->assertDontSee('google-secret-id-12345');
        $response->assertDontSee('secret_hashed_password');

        // Verify Google account linked is shown as boolean (Yes/No)
        $response->assertSee('Google Account Linked:');
        $response->assertSee('Yes');
    }

    public function test_admin_views_soft_deleted_doctor(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-TRASH-01',
            'full_name' => 'Dr. Trashed Doctor',
        ]);
        $doctor->delete();

        $response = $this->actingAs($this->admin, 'doctor')->get("/admin/doctors/{$doctor->doctor_id}");

        $response->assertOk();
        $response->assertSee('DOC-TRASH-01');
        $response->assertSee('Dr. Trashed Doctor (deleted)');
        $response->assertSee('Restore Doctor Account');
    }

    public function test_delete_doctor_who_has_updates_soft_deletes_doctor_keeps_updates_and_author_resolves(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-DEL-01',
            'full_name' => 'Dr. Allison Cameron',
            'email' => 'cameron@hospital.local',
        ]);

        $patient = Patient::factory()->create(['patient_id' => 'PAT-DEL-01']);

        $update = ClinicalUpdate::factory()->create([
            'doctor_id' => $doctor->doctor_id,
            'patient_id' => $patient->patient_id,
            'content' => 'Routine morning round observation.',
        ]);

        $response = $this->actingAs($this->admin, 'doctor')->delete("/admin/doctors/{$doctor->doctor_id}", [
            'confirm_delete' => '1',
        ]);

        $response->assertRedirect('/admin/doctors');
        $response->assertSessionHas('success');

        // Doctor is soft-deleted
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-DEL-01']);

        // Clinical updates remain untouched
        $this->assertDatabaseHas('clinical_updates', ['update_id' => $update->update_id]);

        // Updates relationship resolves the author with withTrashed()
        $freshUpdate = ClinicalUpdate::find($update->update_id);
        $this->assertNotNull($freshUpdate->doctor);
        $this->assertSame('DOC-DEL-01', $freshUpdate->doctor->doctor_id);

        // Display name reflects soft-deletion
        $this->assertSame('Dr. Allison Cameron (deleted)', $freshUpdate->doctor->display_name);

        // Doctor ID and email remain reserved in database
        $this->assertDatabaseHas('doctors', [
            'doctor_id' => 'DOC-DEL-01',
            'email' => 'cameron@hospital.local',
        ]);
    }

    public function test_deleting_doctor_requires_confirmation_checkbox(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-NO-CONFIRM',
        ]);

        // Attempt delete without confirmation checkbox
        $response = $this->actingAs($this->admin, 'doctor')->delete("/admin/doctors/{$doctor->doctor_id}", []);

        $response->assertSessionHasErrors('confirm_delete');
        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-NO-CONFIRM']);
    }

    public function test_restore_soft_deleted_doctor_allows_login_again(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-RESTORE-01',
            'email' => 'restore.doc@hospital.local',
            'password' => 'Password123!',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-RESTORE-01']);

        // Ensure soft-deleted doctor cannot login
        $loginAttempt1 = $this->post('/login', [
            'email' => 'restore.doc@hospital.local',
            'password' => 'Password123!',
        ]);
        $loginAttempt1->assertSessionHasErrors('email');
        $this->assertGuest('doctor');

        // Admin restores the doctor
        $restoreResponse = $this->actingAs($this->admin, 'doctor')
            ->post("/admin/doctors/{$doctor->doctor_id}/restore");

        $restoreResponse->assertRedirect("/admin/doctors/{$doctor->doctor_id}");
        $restoreResponse->assertSessionHas('success');
        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-RESTORE-01']);

        // Log out admin before attempting doctor login
        auth('doctor')->logout();

        // Doctor can now log in again
        $loginAttempt2 = $this->post('/login', [
            'email' => 'restore.doc@hospital.local',
            'password' => 'Password123!',
        ]);
        $loginAttempt2->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($doctor, 'doctor');
    }

    public function test_doctor_cannot_access_any_admin_doctor_route(): void
    {
        $doctor = Doctor::factory()->create();

        $routes = [
            ['GET', '/admin/doctors'],
            ['GET', "/admin/doctors/{$doctor->doctor_id}"],
            ['DELETE', "/admin/doctors/{$doctor->doctor_id}"],
            ['POST', "/admin/doctors/{$doctor->doctor_id}/restore"],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($doctor, 'doctor')->call($method, $uri);
            // Non-admin doctor gets 403 Forbidden on every /admin route
            $response->assertForbidden();
        }
    }
}

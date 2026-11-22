<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AdminAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdminAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminAccountService();
    }

    public function test_paginate_doctors_paginates_20_per_page(): void
    {
        Doctor::factory()->count(25)->create();

        $paginator = $this->service->paginateDoctors([]);

        $this->assertSame(20, $paginator->perPage());
        $this->assertSame(25, $paginator->total());
        $this->assertCount(20, $paginator->items());
    }

    public function test_paginate_doctors_searches_by_id_name_and_email_including_arabic_and_escaped_wildcards(): void
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
        $arabicResults = $this->service->paginateDoctors(['search' => 'أحمد']);
        $this->assertSame(1, $arabicResults->total());
        $this->assertSame('DOC-ARABIC-01', $arabicResults->first()->doctor_id);

        // Search by Doctor ID
        $idResults = $this->service->paginateDoctors(['search' => 'DOC-ENG-02']);
        $this->assertSame(1, $idResults->total());
        $this->assertSame('Dr. Gregory House', $idResults->first()->full_name);

        // Search by Email
        $emailResults = $this->service->paginateDoctors(['search' => 'ahmed@hospital.local']);
        $this->assertSame(1, $emailResults->total());
        $this->assertSame('DOC-ARABIC-01', $emailResults->first()->doctor_id);

        // Search with LIKE wildcards escaped
        $wildcardResults = $this->service->paginateDoctors(['search' => '%_']);
        $this->assertSame(0, $wildcardResults->total());
    }

    public function test_paginate_doctors_filters_by_status(): void
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
        $defaultResults = $this->service->paginateDoctors([]);
        $this->assertSame(1, $defaultResults->total());
        $this->assertSame('DOC-ACTIVE-01', $defaultResults->first()->doctor_id);

        // Filter: active
        $activeResults = $this->service->paginateDoctors(['status' => 'active']);
        $this->assertSame(1, $activeResults->total());
        $this->assertSame('DOC-ACTIVE-01', $activeResults->first()->doctor_id);

        // Filter: deleted
        $deletedResults = $this->service->paginateDoctors(['status' => 'deleted']);
        $this->assertSame(1, $deletedResults->total());
        $this->assertSame('DOC-DELETED-02', $deletedResults->first()->doctor_id);

        // Filter: deactivated (alias)
        $deactivatedResults = $this->service->paginateDoctors(['status' => 'deactivated']);
        $this->assertSame(1, $deactivatedResults->total());
        $this->assertSame('DOC-DELETED-02', $deactivatedResults->first()->doctor_id);

        // Filter: all
        $allResults = $this->service->paginateDoctors(['status' => 'all']);
        $this->assertSame(2, $allResults->total());
    }

    public function test_paginate_doctors_accepts_custom_per_page_and_filter(): void
    {
        Doctor::factory()->count(15)->create();

        $byParam = $this->service->paginateDoctors([], 10);
        $this->assertSame(10, $byParam->perPage());
        $this->assertCount(10, $byParam->items());

        $byFilter = $this->service->paginateDoctors(['per_page' => 5]);
        $this->assertSame(5, $byFilter->perPage());
        $this->assertCount(5, $byFilter->items());
    }

    public function test_paginate_doctors_uses_doctor_id_as_deterministic_tiebreaker(): void
    {
        Doctor::factory()->create(['doctor_id' => 'DOC-B', 'full_name' => 'Identical Name']);
        Doctor::factory()->create(['doctor_id' => 'DOC-A', 'full_name' => 'Identical Name']);

        $page1 = $this->service->paginateDoctors(['per_page' => 1, 'page' => 1]);
        $this->assertSame('DOC-A', $page1->first()->doctor_id);

        $page2 = $this->service->paginateDoctors(['per_page' => 1, 'page' => 2]);
        $this->assertSame('DOC-B', $page2->first()->doctor_id);
    }

    public function test_get_doctor_resolves_active_and_soft_deleted_doctor(): void
    {
        $activeDoc = Doctor::factory()->create(['doctor_id' => 'DOC-ACT-99']);
        $deletedDoc = Doctor::factory()->create(['doctor_id' => 'DOC-DEL-99']);
        $deletedDoc->delete();

        $resolvedActive = $this->service->getDoctor('DOC-ACT-99');
        $this->assertSame('DOC-ACT-99', $resolvedActive->doctor_id);

        $resolvedDeleted = $this->service->getDoctor('DOC-DEL-99');
        $this->assertSame('DOC-DEL-99', $resolvedDeleted->doctor_id);
        $this->assertNotNull($resolvedDeleted->deleted_at);
    }

    public function test_get_doctor_clinical_updates_returns_paginated_updates_with_patient(): void
    {
        $doctor = Doctor::factory()->create(['doctor_id' => 'DOC-UPD-01']);
        $patient = Patient::factory()->create(['patient_id' => 'PAT-UPD-01']);

        ClinicalUpdate::factory()->create([
            'doctor_id' => $doctor->doctor_id,
            'patient_id' => $patient->patient_id,
            'content' => 'First update',
            'created_at' => now()->subHour(),
        ]);

        ClinicalUpdate::factory()->create([
            'doctor_id' => $doctor->doctor_id,
            'patient_id' => $patient->patient_id,
            'content' => 'Second update',
            'created_at' => now(),
        ]);

        $updates = $this->service->getDoctorClinicalUpdates($doctor, 10);

        $this->assertSame(2, $updates->total());
        $this->assertSame('Second update', $updates->first()->content);
        $this->assertTrue($updates->first()->relationLoaded('patient'));
    }

    public function test_delete_doctor_soft_deletes_doctor_and_preserves_clinical_updates_and_reserved_identifiers(): void
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

        $this->service->deleteDoctor($doctor);

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

    public function test_restore_doctor_restores_soft_deleted_doctor(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-RES-01',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-RES-01']);

        $this->service->restoreDoctor($doctor);

        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-RES-01']);
        $this->assertNotNull(Doctor::find('DOC-RES-01'));
    }
}

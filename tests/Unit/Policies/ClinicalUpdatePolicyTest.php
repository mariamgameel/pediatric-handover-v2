<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Policies\ClinicalUpdatePolicy;
use Tests\TestCase;

class ClinicalUpdatePolicyTest extends TestCase
{
    protected ClinicalUpdatePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ClinicalUpdatePolicy();
    }

    public function test_active_doctor_can_view_any_view_and_create(): void
    {
        $doctor = new Doctor();
        $doctor->doctor_id = 'DOC-001';
        $doctor->is_admin = false;

        $update = new ClinicalUpdate();
        $update->doctor_id = 'DOC-002';

        $this->assertTrue($this->policy->viewAny($doctor));
        $this->assertTrue($this->policy->view($doctor, $update));
        $this->assertTrue($this->policy->create($doctor));
    }

    public function test_doctor_can_correct_own_update(): void
    {
        $doctor = new Doctor();
        $doctor->doctor_id = 'DOC-001';
        $doctor->is_admin = false;

        $update = new ClinicalUpdate();
        $update->doctor_id = 'DOC-001';

        $this->assertTrue($this->policy->correct($doctor, $update));
    }

    public function test_doctor_cannot_correct_another_doctors_update(): void
    {
        $doctor = new Doctor();
        $doctor->doctor_id = 'DOC-001';
        $doctor->is_admin = false;

        $update = new ClinicalUpdate();
        $update->doctor_id = 'DOC-002';

        $this->assertFalse($this->policy->correct($doctor, $update));
    }

    public function test_admin_can_correct_another_doctors_update(): void
    {
        $admin = new Doctor();
        $admin->doctor_id = 'DOC-ADMIN';
        $admin->is_admin = true;

        $update = new ClinicalUpdate();
        $update->doctor_id = 'DOC-002';

        $this->assertTrue($this->policy->correct($admin, $update));
    }

    public function test_no_one_can_update_or_delete_clinical_updates_including_admin(): void
    {
        $doctor = new Doctor();
        $doctor->doctor_id = 'DOC-001';
        $doctor->is_admin = false;

        $admin = new Doctor();
        $admin->doctor_id = 'DOC-ADMIN';
        $admin->is_admin = true;

        $update = new ClinicalUpdate();
        $update->doctor_id = 'DOC-001';

        // Non-admin doctor
        $this->assertFalse($this->policy->update($doctor, $update));
        $this->assertFalse($this->policy->delete($doctor, $update));

        // Admin doctor
        $this->assertFalse($this->policy->update($admin, $update));
        $this->assertFalse($this->policy->delete($admin, $update));
    }
}

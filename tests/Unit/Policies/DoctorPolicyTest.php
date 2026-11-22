<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Doctor;
use App\Policies\DoctorPolicy;
use Tests\TestCase;

class DoctorPolicyTest extends TestCase
{
    protected DoctorPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DoctorPolicy();
    }

    public function test_non_admin_doctor_cannot_manage_doctors(): void
    {
        $doctor = new Doctor();
        $doctor->is_admin = false;
        $targetDoctor = new Doctor();

        $this->assertFalse($this->policy->viewAny($doctor));
        $this->assertFalse($this->policy->view($doctor, $targetDoctor));
        $this->assertFalse($this->policy->delete($doctor, $targetDoctor));
        $this->assertFalse($this->policy->restore($doctor, $targetDoctor));
    }

    public function test_admin_doctor_can_manage_doctors_but_cannot_delete_self(): void
    {
        $admin = new Doctor();
        $admin->doctor_id = 'DOC-ADMIN-ME';
        $admin->is_admin = true;

        $targetDoctor = new Doctor();
        $targetDoctor->doctor_id = 'DOC-OTHER';

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $targetDoctor));
        $this->assertTrue($this->policy->restore($admin, $targetDoctor));

        // Can delete other doctor
        $this->assertTrue($this->policy->delete($admin, $targetDoctor));

        // Cannot delete self
        $this->assertFalse($this->policy->delete($admin, $admin));

        // Cannot restore self
        $this->assertFalse($this->policy->restore($admin, $admin));
    }
}

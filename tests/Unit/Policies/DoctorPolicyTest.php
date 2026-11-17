<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Admin;
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

    public function test_doctor_cannot_manage_doctors(): void
    {
        $doctor = new Doctor();
        $targetDoctor = new Doctor();

        $this->assertFalse($this->policy->viewAny($doctor));
        $this->assertFalse($this->policy->view($doctor, $targetDoctor));
        $this->assertFalse($this->policy->delete($doctor, $targetDoctor));
        $this->assertFalse($this->policy->restore($doctor, $targetDoctor));
    }

    public function test_admin_can_manage_doctors(): void
    {
        $admin = new Admin();
        $targetDoctor = new Doctor();

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $targetDoctor));
        $this->assertTrue($this->policy->delete($admin, $targetDoctor));
        $this->assertTrue($this->policy->restore($admin, $targetDoctor));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Policies\PatientPolicy;
use Tests\TestCase;

class PatientPolicyTest extends TestCase
{
    protected PatientPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PatientPolicy();
    }

    public function test_doctor_has_read_only_access_to_patients(): void
    {
        $doctor = new Doctor();
        $patient = new Patient();

        $this->assertTrue($this->policy->viewAny($doctor));
        $this->assertTrue($this->policy->view($doctor, $patient));
        $this->assertFalse($this->policy->create($doctor));
        $this->assertFalse($this->policy->update($doctor, $patient));
        $this->assertFalse($this->policy->delete($doctor, $patient));
    }

    public function test_admin_has_full_management_access_to_patients(): void
    {
        $admin = new Admin();
        $patient = new Patient();

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $patient));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $patient));
        $this->assertTrue($this->policy->delete($admin, $patient));
    }
}

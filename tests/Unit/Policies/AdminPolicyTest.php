<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Admin;
use App\Models\Doctor;
use App\Policies\AdminPolicy;
use Tests\TestCase;

class AdminPolicyTest extends TestCase
{
    protected AdminPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AdminPolicy();
    }

    public function test_doctor_cannot_manage_admins(): void
    {
        $doctor = new Doctor();
        $targetAdmin = new Admin();

        $this->assertFalse($this->policy->viewAny($doctor));
        $this->assertFalse($this->policy->view($doctor, $targetAdmin));
        $this->assertFalse($this->policy->delete($doctor, $targetAdmin));
        $this->assertFalse($this->policy->restore($doctor, $targetAdmin));
    }

    public function test_admin_can_manage_other_admins_but_cannot_delete_self(): void
    {
        $admin = new Admin();
        $admin->admin_id = 1;

        $otherAdmin = new Admin();
        $otherAdmin->admin_id = 2;

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $otherAdmin));
        $this->assertTrue($this->policy->restore($admin, $otherAdmin));

        // Can delete other admin
        $this->assertTrue($this->policy->delete($admin, $otherAdmin));

        // Cannot delete self
        $this->assertFalse($this->policy->delete($admin, $admin));
    }
}

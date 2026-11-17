<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Exceptions\CannotDeleteAdminException;
use App\Models\Admin;
use App\Models\Doctor;
use App\Services\AdminAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin1;
    protected Admin $admin2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin1 = Admin::factory()->create([
            'email' => 'admin1@hospital.local',
            'full_name' => 'First Admin',
        ]);
        $this->admin2 = Admin::factory()->create([
            'email' => 'admin2@hospital.local',
            'full_name' => 'Second Admin',
        ]);
    }

    public function test_admin_can_list_admin_accounts(): void
    {
        $response = $this->actingAs($this->admin1, 'admin')->get('/admin/admins');

        $response->assertOk();
        $response->assertViewIs('admin.admins.index');
        $response->assertSee('admin1@hospital.local');
        $response->assertSee('First Admin');
        $response->assertSee('admin2@hospital.local');
        $response->assertSee('Second Admin');
    }

    public function test_admin_cannot_delete_their_own_account_via_route(): void
    {
        $response = $this->actingAs($this->admin1, 'admin')->delete("/admin/admins/{$this->admin1->admin_id}", [
            'confirm_delete' => '1',
        ]);

        // Policy denies self-deletion -> 403 Forbidden
        $response->assertForbidden();

        $this->assertNotSoftDeleted('admins', ['admin_id' => $this->admin1->admin_id]);
    }

    public function test_admin_cannot_delete_their_own_account_via_service(): void
    {
        $service = app(AdminAccountService::class);

        $this->expectException(CannotDeleteAdminException::class);
        $this->expectExceptionMessage('cannot delete their own account');

        $service->deleteAdmin($this->admin1, $this->admin1);
    }

    public function test_admin_cannot_delete_the_last_active_admin(): void
    {
        // Delete admin2 first so admin1 is the only active admin left
        $this->admin2->delete();

        $this->assertSame(1, Admin::whereNull('deleted_at')->count());

        $service = app(AdminAccountService::class);

        // Another admin context attempting to delete admin1 when admin1 is the last active admin
        $anotherAdmin = Admin::factory()->make(['admin_id' => 999]);

        $this->expectException(CannotDeleteAdminException::class);
        $this->expectExceptionMessage('Cannot delete the last remaining active administrator');

        $service->deleteAdmin($anotherAdmin, $this->admin1);
    }

    public function test_admin_can_delete_another_admin_with_confirmation(): void
    {
        $response = $this->actingAs($this->admin1, 'admin')->delete("/admin/admins/{$this->admin2->admin_id}", [
            'confirm_delete' => '1',
        ]);

        $response->assertRedirect('/admin/admins');
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('admins', ['admin_id' => $this->admin2->admin_id]);
    }

    public function test_deleting_admin_requires_confirmation_checkbox(): void
    {
        $response = $this->actingAs($this->admin1, 'admin')->delete("/admin/admins/{$this->admin2->admin_id}", []);

        $response->assertSessionHasErrors('confirm_delete');
        $this->assertNotSoftDeleted('admins', ['admin_id' => $this->admin2->admin_id]);
    }

    public function test_concurrent_delete_race_is_protected_by_transaction_count_recheck(): void
    {
        $service = app(AdminAccountService::class);

        // Suppose admin1 and admin2 are active.
        // Simulate a race where admin2 is deleted right after the action begins,
        // so when deleteAdmin runs its active count check inside transaction, count is 1.
        $this->admin1->delete(); // Now only admin2 is active

        $fakeCurrentAdmin = Admin::factory()->make(['admin_id' => 9999]);

        $this->expectException(CannotDeleteAdminException::class);
        $this->expectExceptionMessage('Cannot delete the last remaining active administrator');

        $service->deleteAdmin($fakeCurrentAdmin, $this->admin2);
    }

    public function test_soft_deleted_admin_cannot_log_in(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'trashed.admin@hospital.local',
            'password' => 'AdminSecret123!',
        ]);

        $admin->delete();
        $this->assertSoftDeleted('admins', ['admin_id' => $admin->admin_id]);

        $response = $this->post('/admin/login', [
            'email' => 'trashed.admin@hospital.local',
            'password' => 'AdminSecret123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_soft_deleted_admin_session_is_terminated_on_next_request(): void
    {
        // Admin is logged in
        $this->actingAs($this->admin2, 'admin');

        // Soft delete the admin
        $this->admin2->delete();
        $this->assertSoftDeleted('admins', ['admin_id' => $this->admin2->admin_id]);

        // Next request hitting a protected admin route
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/admin/login');
        $response->assertSessionHas('error', 'Your administrator account is inactive.');
        $this->assertGuest('admin');
    }

    public function test_doctor_cannot_access_any_admin_accounts_route(): void
    {
        $doctor = Doctor::factory()->create();

        $routes = [
            ['GET', '/admin/admins'],
            ['DELETE', "/admin/admins/{$this->admin2->admin_id}"],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($doctor, 'doctor')->call($method, $uri);
            $response->assertRedirect('/admin/login');
        }
    }
}

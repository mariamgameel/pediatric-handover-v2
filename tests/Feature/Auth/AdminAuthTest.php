<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin.test@hospital.local',
            'password' => 'AdminSecret123!',
            'last_login_at' => null,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin.test@hospital.local',
            'password' => 'AdminSecret123!',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin, 'admin');

        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    public function test_admin_login_fails_with_wrong_password_giving_identical_error_message(): void
    {
        Admin::factory()->create([
            'email' => 'admin.secure@hospital.local',
            'password' => 'CorrectSecret123!',
        ]);

        $responseWrongPassword = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin.secure@hospital.local',
            'password' => 'WrongSecret!',
        ]);

        $responseWrongPassword->assertRedirect('/admin/login');
        $responseWrongPassword->assertSessionHasErrors('email');
        $wrongPasswordError = session('errors')->first('email');

        $responseUnknownEmail = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'unknown.admin@hospital.local',
            'password' => 'AnyPassword123!',
        ]);

        $responseUnknownEmail->assertRedirect('/admin/login');
        $responseUnknownEmail->assertSessionHasErrors('email');
        $unknownEmailError = session('errors')->first('email');

        $this->assertSame($wrongPasswordError, $unknownEmailError);
        $this->assertSame(trans('auth.failed'), $wrongPasswordError);
    }

    public function test_admin_registration_and_google_login_routes_do_not_exist(): void
    {
        $this->get('/admin/register')->assertNotFound();
        $this->post('/admin/register')->assertNotFound();
        $this->get('/admin/auth/google')->assertNotFound();
    }
}

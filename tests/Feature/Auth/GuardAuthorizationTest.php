<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_to_doctor_route_redirects_to_doctor_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_unauthenticated_request_to_admin_route_redirects_to_admin_login(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/admin/login');
    }

    public function test_doctor_cannot_access_admin_routes(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($doctor, 'doctor')->get('/admin/dashboard');

        // Since doctor is not authenticated under admin guard, auth:admin rejects request
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_cannot_access_doctor_routes(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get('/dashboard');

        // Since admin is not authenticated under doctor guard, auth:doctor rejects request
        $response->assertRedirect('/login');
    }

    public function test_authenticated_doctor_is_redirected_away_from_guest_pages(): void
    {
        $doctor = Doctor::factory()->create();

        $responseLogin = $this->actingAs($doctor, 'doctor')->get('/login');
        $responseLogin->assertRedirect('/dashboard');

        $responseRegister = $this->actingAs($doctor, 'doctor')->get('/register');
        $responseRegister->assertRedirect('/dashboard');
    }

    public function test_authenticated_admin_is_redirected_away_from_guest_pages(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get('/admin/login');
        $response->assertRedirect('/admin/dashboard');

        // Admin visiting doctor login should also be redirected to admin dashboard
        $responseDoctorLogin = $this->actingAs($admin, 'admin')->get('/login');
        $responseDoctorLogin->assertRedirect('/admin/dashboard');
    }
}

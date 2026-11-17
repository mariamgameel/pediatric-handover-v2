<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AdminDoctorRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_admin_routes(): void
    {
        $patient = Patient::factory()->create();

        $routes = [
            '/admin/dashboard',
            '/admin/patients',
            '/admin/patients/create',
            "/admin/patients/{$patient->patient_id}",
            "/admin/patients/{$patient->patient_id}/edit",
            '/admin/doctors',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_signed_in_non_admin_doctor_gets_403_on_every_admin_route(): void
    {
        $doctor = Doctor::factory()->create(['is_admin' => false]);
        $targetDoctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $adminEndpoints = [
            ['GET', '/admin/dashboard'],
            ['GET', '/admin/patients'],
            ['GET', '/admin/patients/create'],
            ['POST', '/admin/patients'],
            ['GET', "/admin/patients/{$patient->patient_id}"],
            ['GET', "/admin/patients/{$patient->patient_id}/edit"],
            ['PUT', "/admin/patients/{$patient->patient_id}"],
            ['DELETE', "/admin/patients/{$patient->patient_id}"],
            ['GET', '/admin/doctors'],
            ['GET', "/admin/doctors/{$targetDoctor->doctor_id}"],
            ['DELETE', "/admin/doctors/{$targetDoctor->doctor_id}"],
            ['POST', "/admin/doctors/{$targetDoctor->doctor_id}/restore"],
        ];

        foreach ($adminEndpoints as [$method, $uri]) {
            $response = $this->actingAs($doctor, 'doctor')->call($method, $uri);
            $response->assertForbidden();
        }
    }

    public function test_admin_doctor_is_allowed_on_admin_routes_and_doctor_routes(): void
    {
        $adminDoctor = Doctor::factory()->admin()->create();
        $patient = Patient::factory()->create();

        // Admin area access
        $this->actingAs($adminDoctor, 'doctor')->get('/admin/dashboard')->assertOk();
        $this->actingAs($adminDoctor, 'doctor')->get('/admin/patients')->assertOk();
        $this->actingAs($adminDoctor, 'doctor')->get('/admin/doctors')->assertOk();

        // Doctor area access
        $this->actingAs($adminDoctor, 'doctor')->get('/dashboard')->assertOk();
        $this->actingAs($adminDoctor, 'doctor')->get('/profile')->assertOk();
        $this->actingAs($adminDoctor, 'doctor')->get('/patients')->assertOk();
        $this->actingAs($adminDoctor, 'doctor')->get("/patients/{$patient->patient_id}")->assertOk();
    }

    public function test_admin_signs_in_at_login_with_password(): void
    {
        $adminDoctor = Doctor::factory()->admin()->create([
            'email' => 'head.admin@hospital.local',
            'password' => 'SecurePass123!',
        ]);

        $response = $this->post('/login', [
            'email' => 'head.admin@hospital.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($adminDoctor, 'doctor');
        $this->assertTrue(auth('doctor')->user()->isAdmin());
    }

    public function test_admin_signs_in_at_login_via_google(): void
    {
        $adminDoctor = Doctor::factory()->admin()->create([
            'email' => 'admin.oauth@hospital.local',
            'google_id' => 'google-admin-id-12345',
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-admin-id-12345');
        $googleUser->shouldReceive('getEmail')->andReturn('admin.oauth@hospital.local');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($adminDoctor, 'doctor');
        $this->assertTrue(auth('doctor')->user()->isAdmin());
    }

    public function test_soft_deleted_admin_cannot_sign_in_and_live_session_ends(): void
    {
        $adminDoctor = Doctor::factory()->admin()->create([
            'email' => 'trashed.admin@hospital.local',
            'password' => 'SecurePass123!',
        ]);

        // 1. Soft-delete doctor and ensure cannot sign in
        $adminDoctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $adminDoctor->doctor_id]);

        $response = $this->post('/login', [
            'email' => 'trashed.admin@hospital.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('doctor');

        // 2. Restore, log in, soft delete during live session, and assert next request ends session
        $adminDoctor->restore();

        $this->actingAs($adminDoctor, 'doctor');
        $this->get('/admin/dashboard')->assertOk();

        // Soft-delete the doctor while session is active
        $adminDoctor->delete();

        // Next request hitting protected route
        $nextResponse = $this->get('/admin/dashboard');
        $nextResponse->assertRedirect('/login');
        $nextResponse->assertSessionHas('error');
        $this->assertGuest('doctor');
    }
}

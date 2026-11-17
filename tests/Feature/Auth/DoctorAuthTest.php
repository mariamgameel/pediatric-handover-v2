<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_login_with_valid_credentials(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doctor@hospital.local',
            'password' => 'SecretPassword123!',
            'last_login_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'doctor@hospital.local',
            'password' => 'SecretPassword123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($doctor, 'doctor');

        $doctor->refresh();
        $this->assertNotNull($doctor->last_login_at);
    }

    public function test_doctor_can_logout(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($doctor, 'doctor')->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest('doctor');
    }

    public function test_wrong_password_and_unknown_email_give_identical_error_message(): void
    {
        Doctor::factory()->create([
            'email' => 'existing.doctor@hospital.local',
            'password' => 'CorrectPassword123!',
        ]);

        // Wrong password for existing doctor
        $responseWrongPassword = $this->from('/login')->post('/login', [
            'email' => 'existing.doctor@hospital.local',
            'password' => 'WrongPassword!',
        ]);

        $responseWrongPassword->assertRedirect('/login');
        $responseWrongPassword->assertSessionHasErrors('email');
        $wrongPasswordError = session('errors')->first('email');

        // Unknown email
        $responseUnknownEmail = $this->from('/login')->post('/login', [
            'email' => 'nonexistent.doctor@hospital.local',
            'password' => 'AnyPassword123!',
        ]);

        $responseUnknownEmail->assertRedirect('/login');
        $responseUnknownEmail->assertSessionHasErrors('email');
        $unknownEmailError = session('errors')->first('email');

        $this->assertSame($wrongPasswordError, $unknownEmailError);
        $this->assertSame(trans('auth.failed'), $wrongPasswordError);
    }

    public function test_soft_deleted_doctor_cannot_login(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'deleted.doctor@hospital.local',
            'password' => 'Secret123!',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'deleted.doctor@hospital.local',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('doctor');
        $this->assertSame(trans('auth.failed'), session('errors')->first('email'));
    }

    public function test_soft_deleted_doctor_with_existing_session_is_logged_out_on_next_request(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-ACTIVE-01',
        ]);

        // Doctor is logged in
        $this->actingAs($doctor, 'doctor');

        // Soft delete the doctor in the database
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-ACTIVE-01']);

        // Next request hitting a protected doctor route
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest('doctor');
    }
}

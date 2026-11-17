<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_register_successfully(): void
    {
        $response = $this->post('/register', [
            'doctor_id' => 'DOC-10001',
            'full_name' => 'Dr. Elizabeth Stone',
            'email' => 'elizabeth.stone@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone_number' => '+1234567890',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs(Doctor::find('DOC-10001'), 'doctor');

        $this->assertDatabaseHas('doctors', [
            'doctor_id' => 'DOC-10001',
            'email' => 'elizabeth.stone@hospital.local',
            'full_name' => 'Dr. Elizabeth Stone',
            'phone_number' => '+1234567890',
        ]);

        $doctor = Doctor::find('DOC-10001');
        $this->assertNotNull($doctor?->last_login_at);
    }

    public function test_registration_fails_on_duplicate_doctor_id(): void
    {
        Doctor::factory()->create([
            'doctor_id' => 'DOC-EXISTING',
            'email' => 'first.doctor@hospital.local',
        ]);

        $response = $this->post('/register', [
            'doctor_id' => 'DOC-EXISTING',
            'full_name' => 'Another Doctor',
            'email' => 'second.doctor@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['doctor_id']);
        $this->assertGuest('doctor');
    }

    public function test_registration_fails_on_duplicate_email(): void
    {
        Doctor::factory()->create([
            'doctor_id' => 'DOC-01',
            'email' => 'shared@hospital.local',
        ]);

        $response = $this->post('/register', [
            'doctor_id' => 'DOC-02',
            'full_name' => 'Dr. New',
            'email' => 'shared@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('doctor');
    }

    public function test_re_registering_a_soft_deleted_doctor_id_is_rejected(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-DELETED-ID',
            'email' => 'old.email@hospital.local',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-DELETED-ID']);

        $response = $this->post('/register', [
            'doctor_id' => 'DOC-DELETED-ID',
            'full_name' => 'New Doctor Reusing ID',
            'email' => 'new.email@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['doctor_id']);
        $this->assertGuest('doctor');
    }

    public function test_re_registering_a_soft_deleted_doctor_email_is_rejected(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-OLD-01',
            'email' => 'deleted.doctor@hospital.local',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['email' => 'deleted.doctor@hospital.local']);

        $response = $this->post('/register', [
            'doctor_id' => 'DOC-NEW-02',
            'full_name' => 'New Doctor Reusing Email',
            'email' => 'deleted.doctor@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('doctor');
    }
}

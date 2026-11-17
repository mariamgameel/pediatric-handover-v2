<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_access_to_profile_redirects_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/profile/edit')->assertRedirect('/login');
        $this->patch('/profile', ['full_name' => 'Attempted Edit'])->assertRedirect('/login');
    }

    public function test_admin_doctor_can_access_profile_routes(): void
    {
        $admin = Doctor::factory()->admin()->create();

        $this->actingAs($admin, 'doctor')->get('/profile')->assertOk();
        $this->actingAs($admin, 'doctor')->get('/profile/edit')->assertOk();
    }

    public function test_doctor_can_view_own_profile_without_exposing_sensitive_fields(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-VIEW-01',
            'full_name' => 'Dr. Gregory House',
            'email' => 'house@hospital.local',
            'phone_number' => '+1555123456',
            'google_id' => 'secret-google-id-XYZ999',
            'remember_token' => 'sensitive-remember-token-123',
        ]);

        $response = $this->actingAs($doctor, 'doctor')->get('/profile');

        $response->assertOk();
        $response->assertSee('Dr. Gregory House');
        $response->assertSee('DOC-VIEW-01');
        $response->assertSee('house@hospital.local');
        $response->assertSee('+1555123456');
        $response->assertSee('Google Account Linked:');
        $response->assertSee('Yes');

        // Sensitive fields must never appear
        $response->assertDontSee('secret-google-id-XYZ999');
        $response->assertDontSee('sensitive-remember-token-123');
        $response->assertDontSee($doctor->password);
    }

    public function test_doctor_can_update_profile_fields_successfully(): void
    {
        $doctor = Doctor::factory()->create([
            'full_name' => 'Dr. Original Name',
            'email' => 'original@hospital.local',
            'phone_number' => '+1111111111',
        ]);

        $response = $this->actingAs($doctor, 'doctor')->patch('/profile', [
            'full_name' => 'Dr. Updated Name',
            'email' => 'updated@hospital.local',
            'phone_number' => '+2222222222',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        $doctor->refresh();
        $this->assertSame('Dr. Updated Name', $doctor->full_name);
        $this->assertSame('updated@hospital.local', $doctor->email);
        $this->assertSame('+2222222222', $doctor->phone_number);
    }

    public function test_doctor_can_keep_own_email_unchanged(): void
    {
        $doctor = Doctor::factory()->create([
            'full_name' => 'Dr. Same Email',
            'email' => 'same.email@hospital.local',
        ]);

        $response = $this->actingAs($doctor, 'doctor')->patch('/profile', [
            'full_name' => 'Dr. Name Changed Only',
            'email' => 'same.email@hospital.local',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        $doctor->refresh();
        $this->assertSame('Dr. Name Changed Only', $doctor->full_name);
        $this->assertSame('same.email@hospital.local', $doctor->email);
    }

    public function test_doctor_can_clear_phone_number_to_null(): void
    {
        $doctor = Doctor::factory()->create([
            'phone_number' => '+1234567890',
        ]);

        $response = $this->actingAs($doctor, 'doctor')->patch('/profile', [
            'full_name' => $doctor->full_name,
            'email' => $doctor->email,
            'phone_number' => null,
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        $doctor->refresh();
        $this->assertNull($doctor->phone_number);
    }

    public function test_duplicate_email_with_another_active_doctor_is_rejected(): void
    {
        Doctor::factory()->create(['email' => 'other.doctor@hospital.local']);

        $currentDoctor = Doctor::factory()->create(['email' => 'current@hospital.local']);

        $response = $this->actingAs($currentDoctor, 'doctor')->patch('/profile', [
            'full_name' => $currentDoctor->full_name,
            'email' => 'other.doctor@hospital.local',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $currentDoctor->refresh();
        $this->assertSame('current@hospital.local', $currentDoctor->email);
    }

    public function test_duplicate_email_with_a_soft_deleted_doctor_is_rejected(): void
    {
        $deletedDoctor = Doctor::factory()->create(['email' => 'archived.doc@hospital.local']);
        $deletedDoctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $deletedDoctor->doctor_id]);

        $currentDoctor = Doctor::factory()->create(['email' => 'current.active@hospital.local']);

        $response = $this->actingAs($currentDoctor, 'doctor')->patch('/profile', [
            'full_name' => $currentDoctor->full_name,
            'email' => 'archived.doc@hospital.local',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $currentDoctor->refresh();
        $this->assertSame('current.active@hospital.local', $currentDoctor->email);
    }

    public function test_attempt_to_submit_doctor_id_is_rejected_with_422_and_leaves_id_unchanged(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-IMMUTABLE-01',
        ]);

        $response = $this->actingAs($doctor, 'doctor')
            ->from('/profile/edit')
            ->patch('/profile', [
                'doctor_id' => 'DOC-HACKED-99',
                'full_name' => 'Dr. New Name',
                'email' => $doctor->email,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('doctor_id');

        $doctor->refresh();
        $this->assertSame('DOC-IMMUTABLE-01', $doctor->doctor_id);
    }

    public function test_attempt_to_submit_google_id_or_password_is_rejected_with_422(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'legit-gid-123',
            'password' => 'InitialPassword!',
        ]);
        $originalPasswordHash = $doctor->password;

        $responseGoogle = $this->actingAs($doctor, 'doctor')
            ->from('/profile/edit')
            ->patch('/profile', [
                'google_id' => 'evil-gid-666',
                'full_name' => $doctor->full_name,
                'email' => $doctor->email,
            ]);

        $responseGoogle->assertStatus(302);
        $responseGoogle->assertSessionHasErrors('google_id');

        $doctor->refresh();
        $this->assertSame('legit-gid-123', $doctor->google_id);

        $responsePassword = $this->actingAs($doctor, 'doctor')
            ->from('/profile/edit')
            ->patch('/profile', [
                'password' => 'HackedPassword123!',
                'full_name' => $doctor->full_name,
                'email' => $doctor->email,
            ]);

        $responsePassword->assertStatus(302);
        $responsePassword->assertSessionHasErrors('password');

        $doctor->refresh();
        $this->assertSame($originalPasswordHash, $doctor->password);
    }

    public function test_google_id_remains_untouched_after_email_change(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'bound-google-account-777',
            'email' => 'old.email@hospital.local',
        ]);

        $response = $this->actingAs($doctor, 'doctor')->patch('/profile', [
            'full_name' => $doctor->full_name,
            'email' => 'brand.new.email@hospital.local',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        $doctor->refresh();
        $this->assertSame('brand.new.email@hospital.local', $doctor->email);
        $this->assertSame('bound-google-account-777', $doctor->google_id);
    }
}

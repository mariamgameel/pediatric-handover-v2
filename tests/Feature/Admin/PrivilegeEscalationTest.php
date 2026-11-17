<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_with_is_admin_in_payload_is_rejected_with_422(): void
    {
        $payloads = [
            [
                'doctor_id' => 'DOC-ESC-01',
                'full_name' => 'Dr. Evil',
                'email' => 'evil1@hospital.local',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'is_admin' => true,
            ],
            [
                'doctor_id' => 'DOC-ESC-02',
                'full_name' => 'Dr. Sneak',
                'email' => 'sneak@hospital.local',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'is_admin' => '1',
            ],
            [
                'doctor_id' => 'DOC-ESC-03',
                'full_name' => 'Dr. Trick',
                'email' => 'trick@hospital.local',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'is_admin' => false,
            ],
        ];

        foreach ($payloads as $payload) {
            $response = $this->postJson('/register', $payload);
            $response->assertStatus(422);
            $response->assertJsonValidationErrors('is_admin');
            $this->assertDatabaseMissing('doctors', ['doctor_id' => $payload['doctor_id']]);
        }
    }

    public function test_patch_profile_with_is_admin_is_rejected_with_422_and_value_unchanged(): void
    {
        $doctor = Doctor::factory()->create([
            'is_admin' => false,
        ]);

        $response = $this->actingAs($doctor, 'doctor')->patchJson('/profile', [
            'full_name' => 'Dr. Hacked Name',
            'email' => $doctor->email,
            'is_admin' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_admin');

        $doctor->refresh();
        $this->assertFalse($doctor->isAdmin());
        $this->assertNotSame('Dr. Hacked Name', $doctor->full_name);
    }

    public function test_google_sign_in_never_creates_an_admin(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'regular.doc@hospital.local',
            'google_id' => null,
            'is_admin' => false,
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-regular-id-777');
        $googleUser->shouldReceive('getEmail')->andReturn('regular.doc@hospital.local');
        $googleUser->user = ['email_verified' => true, 'is_admin' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($doctor, 'doctor');

        $doctor->refresh();
        $this->assertSame('google-regular-id-777', $doctor->google_id);
        $this->assertFalse($doctor->isAdmin());
    }

    public function test_mass_assignment_of_is_admin_is_silently_ignored(): void
    {
        // 1. Doctor::create
        $doctor = Doctor::create([
            'doctor_id' => 'DOC-MASS-01',
            'full_name' => 'Dr. Mass Assignment',
            'email' => 'mass@hospital.local',
            'password' => 'Password123!',
            'is_admin' => true,
        ]);

        $this->assertFalse($doctor->isAdmin());
        $this->assertFalse((bool) $doctor->fresh()->is_admin);

        // 2. Doctor::fill
        $doctor->fill(['is_admin' => true]);
        $doctor->save();

        $this->assertFalse($doctor->isAdmin());
        $this->assertFalse((bool) $doctor->fresh()->is_admin);

        // 3. Doctor::update
        $doctor->update(['is_admin' => true]);

        $this->assertFalse($doctor->isAdmin());
        $this->assertFalse((bool) $doctor->fresh()->is_admin);
    }
}

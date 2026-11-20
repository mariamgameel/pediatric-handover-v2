<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_register_via_api_successfully(): void
    {
        $payload = [
            'doctor_id' => 'DOC-API-001',
            'full_name' => 'Dr. Jane Smith',
            'email' => 'jane.smith@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone_number' => '+201012345678',
            'device_name' => 'iPhone 15 Pro',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'token_type',
                'doctor' => [
                    'doctor_id',
                    'full_name',
                    'email',
                    'phone_number',
                    'is_admin',
                    'is_google_linked',
                    'last_login_at',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        $response->assertJson([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'doctor' => [
                    'doctor_id' => 'DOC-API-001',
                    'full_name' => 'Dr. Jane Smith',
                    'email' => 'jane.smith@hospital.local',
                    'phone_number' => '+201012345678',
                    'is_admin' => false,
                    'is_google_linked' => false,
                ],
            ],
        ]);

        $this->assertDatabaseHas('doctors', [
            'doctor_id' => 'DOC-API-001',
            'email' => 'jane.smith@hospital.local',
            'is_admin' => false,
        ]);

        $doctor = Doctor::findOrFail('DOC-API-001');
        $this->assertCount(1, $doctor->tokens);
        $this->assertSame('iPhone 15 Pro', $doctor->tokens->first()->name);
        $this->assertNotNull($doctor->last_login_at);
    }

    public function test_doctor_registration_fails_on_duplicate_email(): void
    {
        Doctor::factory()->create([
            'doctor_id' => 'DOC-EXIST-01',
            'email' => 'existing@hospital.local',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-API-002',
            'full_name' => 'Dr. Another',
            'email' => 'existing@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation failed.',
            'error' => [
                'code' => 'validation_failed',
            ],
        ]);
        $response->assertJsonValidationErrors('email');
    }

    public function test_doctor_registration_fails_on_duplicate_doctor_id(): void
    {
        Doctor::factory()->create([
            'doctor_id' => 'DOC-EXIST-02',
            'email' => 'first@hospital.local',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-EXIST-02',
            'full_name' => 'Dr. Another',
            'email' => 'second@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('doctor_id');
    }

    public function test_doctor_registration_fails_when_password_not_confirmed_or_weak(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-API-003',
            'full_name' => 'Dr. Weak Password',
            'email' => 'weak@hospital.local',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_doctor_registration_fails_when_is_admin_is_provided(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-API-004',
            'full_name' => 'Dr. Hacker',
            'email' => 'hacker@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_admin' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_admin');
        $this->assertDatabaseMissing('doctors', ['doctor_id' => 'DOC-API-004']);
    }

    public function test_re_registering_a_soft_deleted_doctor_id_is_rejected(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-DELETED-ID',
            'email' => 'old.email@hospital.local',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-DELETED-ID']);

        $response = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-DELETED-ID',
            'full_name' => 'New Doctor Reusing ID',
            'email' => 'new.email@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('doctor_id');
    }

    public function test_re_registering_a_soft_deleted_doctor_email_is_rejected(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-OLD-01',
            'email' => 'deleted.doctor@hospital.local',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['email' => 'deleted.doctor@hospital.local']);

        $response = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-NEW-02',
            'full_name' => 'New Doctor Reusing Email',
            'email' => 'deleted.doctor@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_doctor_registration_is_rate_limited(): void
    {
        $payload = [
            'doctor_id' => 'DOC-LIMIT',
            'full_name' => 'Dr. Rate Limit',
            'email' => 'limit@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // 10 attempts per minute allowed by api-register
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/register', $payload);
        }

        $response = $this->postJson('/api/v1/auth/register', $payload);
        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'too_many_requests',
            ],
        ]);
    }
}

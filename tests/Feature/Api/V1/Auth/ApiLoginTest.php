<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_login_with_valid_credentials(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'valid.doc@hospital.local',
            'password' => 'Password123!',
            'is_admin' => false,
            'last_login_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'valid.doc@hospital.local',
            'password' => 'Password123!',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertStatus(200);
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
                    'doctor_id' => $doctor->doctor_id,
                    'email' => 'valid.doc@hospital.local',
                    'is_admin' => false,
                ],
            ],
        ]);

        $doctor->refresh();
        $this->assertNotNull($doctor->last_login_at);
        $this->assertCount(1, $doctor->tokens);
        $this->assertSame('Pixel 8', $doctor->tokens->first()->name);
        $this->assertSame(['doctor'], $doctor->tokens->first()->abilities);
    }

    public function test_admin_doctor_login_issues_token_with_admin_ability(): void
    {
        $admin = Doctor::factory()->admin()->create([
            'email' => 'admin.doc@hospital.local',
            'password' => 'AdminPass123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin.doc@hospital.local',
            'password' => 'AdminPass123!',
            'device_name' => 'Admin Dashboard Laptop',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.doctor.is_admin', true);

        $admin->refresh();
        $this->assertSame(['doctor', 'admin'], $admin->tokens->first()->abilities);
    }

    public function test_doctor_login_fails_with_wrong_password(): void
    {
        Doctor::factory()->create([
            'email' => 'wrongpass@hospital.local',
            'password' => 'CorrectPassword123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrongpass@hospital.local',
            'password' => 'IncorrectPassword!',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid email or password.',
            'error' => [
                'code' => 'invalid_credentials',
            ],
        ]);
    }

    public function test_doctor_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@hospital.local',
            'password' => 'SomePassword123!',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'invalid_credentials',
            ],
        ]);
    }

    public function test_doctor_login_fails_if_doctor_is_soft_deleted(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'deleted.doc@hospital.local',
            'password' => 'Password123!',
        ]);
        $doctor->delete();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'deleted.doc@hospital.local',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
            ],
        ]);
    }

    public function test_doctor_login_is_rate_limited_after_5_attempts(): void
    {
        $payload = [
            'email' => 'ratelimit.api@hospital.local',
            'password' => 'WrongPassword!',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload);
        }

        $response = $this->postJson('/api/v1/auth/login', $payload);
        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'too_many_requests',
            ],
        ]);
    }
}

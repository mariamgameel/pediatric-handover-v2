<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_doctor_can_fetch_me_profile(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-ME-001',
            'full_name' => 'Dr. Me Profile',
            'email' => 'me@hospital.local',
            'phone_number' => '+201111111111',
            'is_admin' => false,
        ]);

        $token = $doctor->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'doctor' => [
                    'doctor_id' => 'DOC-ME-001',
                    'full_name' => 'Dr. Me Profile',
                    'email' => 'me@hospital.local',
                    'phone_number' => '+201111111111',
                    'is_admin' => false,
                    'is_google_linked' => false,
                ],
            ],
        ]);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $doctor = Doctor::factory()->create();

        $tokenPhone = $doctor->createToken('Mobile Phone')->plainTextToken;
        $tokenLaptop = $doctor->createToken('Work Laptop')->plainTextToken;

        $this->assertCount(2, $doctor->tokens);

        // Logout using phone token
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenPhone)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);

        $doctor->refresh();
        $this->assertCount(1, $doctor->tokens);
        $this->assertSame('Work Laptop', $doctor->tokens->first()->name);

        // Phone token is now revoked
        $responsePhoneCheck = $this->withHeader('Authorization', 'Bearer ' . $tokenPhone)
            ->getJson('/api/v1/auth/me');
        $responsePhoneCheck->assertStatus(401);

        // Laptop token still works
        $responseLaptopCheck = $this->withHeader('Authorization', 'Bearer ' . $tokenLaptop)
            ->getJson('/api/v1/auth/me');
        $responseLaptopCheck->assertStatus(200);
    }

    public function test_logout_all_revokes_all_tokens(): void
    {
        $doctor = Doctor::factory()->create();

        $token1 = $doctor->createToken('Device 1')->plainTextToken;
        $token2 = $doctor->createToken('Device 2')->plainTextToken;
        $token3 = $doctor->createToken('Device 3')->plainTextToken;

        $this->assertCount(3, $doctor->tokens);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/v1/auth/logout-all');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Logged out from all devices.',
        ]);

        $doctor->refresh();
        $this->assertCount(0, $doctor->tokens);

        // All tokens are revoked
        $this->withHeader('Authorization', 'Bearer ' . $token1)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer ' . $token2)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer ' . $token3)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_soft_deleted_doctor_token_returns_401(): void
    {
        $doctor = Doctor::factory()->create();
        $token = $doctor->createToken('Device')->plainTextToken;

        // Verify token works while active
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        // Soft delete the doctor directly in DB and reset cached guard user
        $doctor->delete();
        auth()->forgetGuards();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);

        // Attempting to access protected endpoint with the token now returns 401
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
            ],
        ]);
    }
}

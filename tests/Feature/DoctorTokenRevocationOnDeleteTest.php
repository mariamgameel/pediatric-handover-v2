<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DoctorTokenRevocationOnDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_doctor_deletes_all_sanctum_tokens(): void
    {
        $doctor = Doctor::factory()->create([
            'password' => 'Password123!',
        ]);

        $token1 = $doctor->createToken('phone')->plainTextToken;
        $token2 = $doctor->createToken('tablet')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 2);

        // Soft delete the doctor
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);

        // Tokens table must have no rows for this doctor
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $doctor->doctor_id)->count());
    }

    public function test_restoring_soft_deleted_doctor_does_not_bring_back_old_tokens_and_login_creates_new_working_token(): void
    {
        $doctor = Doctor::factory()->create([
            'password' => 'Password123!',
        ]);

        $oldToken = $doctor->createToken('old-phone')->plainTextToken;

        // Deleting doctor revokes tokens
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $doctor->doctor_id)->count());

        // Restoring doctor does not restore old tokens
        $doctor->restore();
        $this->assertNotSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $doctor->doctor_id)->count());

        // Token created before deletion returns 401 on GET /api/v1/auth/me
        $responseOldToken = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/v1/auth/me');
        $responseOldToken->assertStatus(401);
        $responseOldToken->assertJson([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
            ],
        ]);

        // Fresh login returns 200 with new token
        $responseLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $doctor->email,
            'password' => 'Password123!',
            'device_name' => 'new-phone',
        ]);
        $responseLogin->assertStatus(200);

        $newToken = $responseLogin->json('data.token');
        $this->assertNotNull($newToken);

        // New token works on GET /api/v1/auth/me
        $responseMe = $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/v1/auth/me');
        $responseMe->assertStatus(200);
        $responseMe->assertJson([
            'success' => true,
            'data' => [
                'doctor' => [
                    'doctor_id' => $doctor->doctor_id,
                ],
            ],
        ]);
    }
}

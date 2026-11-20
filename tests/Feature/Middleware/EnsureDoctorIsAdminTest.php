<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureDoctorIsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'admin'])->get('/_test/admin-endpoint', function () {
            return response()->json([
                'success' => true,
                'data' => 'admin_granted',
            ]);
        });
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/_test/admin-endpoint');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
            ],
        ]);
    }

    public function test_non_admin_doctor_is_rejected_with_403_forbidden(): void
    {
        $doctor = Doctor::factory()->create([
            'is_admin' => false,
        ]);
        $token = $doctor->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/admin-endpoint');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Administrator access required.',
            'error' => [
                'code' => 'forbidden',
            ],
        ]);
    }

    public function test_admin_doctor_is_allowed_access(): void
    {
        $admin = Doctor::factory()->admin()->create();
        $token = $admin->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/admin-endpoint');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => 'admin_granted',
        ]);
    }

    public function test_token_with_admin_ability_on_non_admin_doctor_is_rejected_using_live_database_check(): void
    {
        $doctor = Doctor::factory()->create([
            'is_admin' => false,
        ]);

        // Attempt privilege escalation via token abilities claim
        $token = $doctor->createToken('hacked-device', ['admin', '*'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/admin-endpoint');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Administrator access required.',
            'error' => [
                'code' => 'forbidden',
            ],
        ]);
    }
}

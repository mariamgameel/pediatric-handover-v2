<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Profile;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_to_profile_returns_401(): void
    {
        $response = $this->patchJson('/api/v1/profile', [
            'full_name' => 'Dr. Unauthorized',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
            ],
        ]);
    }

    public function test_soft_deleted_doctor_token_returns_401(): void
    {
        $doctor = Doctor::factory()->create();
        $token = $doctor->createToken('test-device')->plainTextToken;

        $doctor->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'full_name' => 'Dr. Dead Account',
            ]);

        $response->assertStatus(401);
    }

    public function test_partial_update_updates_only_submitted_fields(): void
    {
        $doctor = Doctor::factory()->create([
            'full_name' => 'Dr. Original',
            'email' => 'original@hospital.local',
            'phone_number' => '+1234567890',
        ]);
        $token = $doctor->createToken('test-device')->plainTextToken;

        // 1. Update only full_name
        $responseName = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'full_name' => 'Dr. Updated Name',
            ]);

        $responseName->assertStatus(200);
        $responseName->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'doctor' => [
                    'full_name' => 'Dr. Updated Name',
                    'email' => 'original@hospital.local',
                    'phone_number' => '+1234567890',
                ],
            ],
        ]);

        $doctor->refresh();
        $this->assertSame('Dr. Updated Name', $doctor->full_name);
        $this->assertSame('original@hospital.local', $doctor->email);
        $this->assertSame('+1234567890', $doctor->phone_number);

        // 2. Update only phone_number
        $responsePhone = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'phone_number' => '+9876543210',
            ]);

        $responsePhone->assertStatus(200);
        $doctor->refresh();
        $this->assertSame('Dr. Updated Name', $doctor->full_name);
        $this->assertSame('+9876543210', $doctor->phone_number);
    }

    public function test_phone_number_null_or_empty_clears_it(): void
    {
        $doctor = Doctor::factory()->create([
            'phone_number' => '+1234567890',
        ]);
        $token = $doctor->createToken('test-device')->plainTextToken;

        // Null clears phone number
        $responseNull = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'phone_number' => null,
            ]);

        $responseNull->assertStatus(200);
        $doctor->refresh();
        $this->assertNull($doctor->phone_number);

        // Empty string also clears phone number
        auth()->forgetGuards();
        $doctor->update(['phone_number' => '+111222333']);
        $responseEmpty = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'phone_number' => '',
            ]);

        $responseEmpty->assertStatus(200);
        $doctor->refresh();
        $this->assertNull($doctor->phone_number);
    }

    public function test_invalid_phone_number_format_returns_422(): void
    {
        $doctor = Doctor::factory()->create();
        $token = $doctor->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'phone_number' => 'invalid-abc-phone!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone_number');
    }

    public function test_empty_payload_returns_422(): void
    {
        $doctor = Doctor::factory()->create();
        $token = $doctor->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payload');
    }

    public function test_prohibited_fields_are_rejected_with_422_and_database_remains_unchanged(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-IMMUTABLE-01',
            'google_id' => 'google-sub-fixed',
            'is_admin' => false,
            'password' => Hash::make('Password123!'),
        ]);
        $token = $doctor->createToken('test-device')->plainTextToken;

        $prohibitedPayloads = [
            'doctor_id' => 'DOC-HACKED-99',
            'google_id' => 'google-hacked',
            'password' => 'NewHackedPassword!',
            'last_login_at' => now()->toIso8601String(),
            'remember_token' => 'stolen-token',
            'profile_image' => 'hacked.png',
            'deleted_at' => now()->toIso8601String(),
            'is_admin' => true,
        ];

        foreach ($prohibitedPayloads as $field => $val) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->patchJson('/api/v1/profile', [
                    'full_name' => 'Attempted Change',
                    $field => $val,
                ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors($field);

            $doctor->refresh();
            $this->assertSame('DOC-IMMUTABLE-01', $doctor->doctor_id);
            $this->assertSame('google-sub-fixed', $doctor->google_id);
            $this->assertFalse($doctor->isAdmin());
        }
    }

    public function test_is_admin_in_payload_cannot_escalate_privileges(): void
    {
        $doctor = Doctor::factory()->create(['is_admin' => false]);
        $token = $doctor->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'full_name' => 'Dr. Normal',
                'is_admin' => true,
            ]);

        $response->assertStatus(422);
        $doctor->refresh();
        $this->assertFalse($doctor->isAdmin());
    }

    public function test_email_uniqueness_checks_active_and_soft_deleted_doctors(): void
    {
        $currentDoctor = Doctor::factory()->create([
            'email' => 'current@hospital.local',
            'password' => 'Password123!',
        ]);
        $token = $currentDoctor->createToken('test-device')->plainTextToken;

        $otherActive = Doctor::factory()->create(['email' => 'other.active@hospital.local']);
        $otherDeleted = Doctor::factory()->create(['email' => 'other.deleted@hospital.local']);
        $otherDeleted->delete();

        // 1. Duplicate of active doctor fails
        $responseActive = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'email' => 'other.active@hospital.local',
                'current_password' => 'Password123!',
            ]);
        $responseActive->assertStatus(422);
        $responseActive->assertJsonValidationErrors('email');

        // 2. Duplicate of soft-deleted doctor fails
        $responseDeleted = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'email' => 'other.deleted@hospital.local',
                'current_password' => 'Password123!',
            ]);
        $responseDeleted->assertStatus(422);
        $responseDeleted->assertJsonValidationErrors('email');

        // 3. Current doctor's own email re-sent is accepted without current_password
        $responseSame = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'email' => 'current@hospital.local',
                'full_name' => 'Dr. Name Updated Only',
            ]);
        $responseSame->assertStatus(200);
        $currentDoctor->refresh();
        $this->assertSame('current@hospital.local', $currentDoctor->email);
        $this->assertSame('Dr. Name Updated Only', $currentDoctor->full_name);
    }

    public function test_resending_own_current_email_returns_200_without_current_password_and_does_not_revoke_other_tokens(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doc.own@hospital.local',
            'password' => Hash::make('Password123!'),
        ]);

        $currentToken = $doctor->createToken('device-current')->plainTextToken;
        $otherToken = $doctor->createToken('device-other')->plainTextToken;

        $this->assertSame(2, $doctor->tokens()->count());

        $response = $this->withHeader('Authorization', 'Bearer ' . $currentToken)
            ->patchJson('/api/v1/profile', [
                'email' => 'doc.own@hospital.local',
                'full_name' => 'Dr. Own Name Updated',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'doctor' => [
                    'email' => 'doc.own@hospital.local',
                    'full_name' => 'Dr. Own Name Updated',
                ],
            ],
        ]);

        $doctor->refresh();
        $this->assertSame('doc.own@hospital.local', $doctor->email);
        $this->assertSame('Dr. Own Name Updated', $doctor->full_name);
        $this->assertSame(2, $doctor->tokens()->count());
    }

    public function test_email_change_with_correct_current_password_updates_email_and_revokes_other_tokens(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doc.before@hospital.local',
            'password' => Hash::make('SecretPass123!'),
        ]);

        $currentToken = $doctor->createToken('device-current')->plainTextToken;
        $otherToken1 = $doctor->createToken('device-other-1')->plainTextToken;
        $otherToken2 = $doctor->createToken('device-other-2')->plainTextToken;

        $this->assertSame(3, $doctor->tokens()->count());

        $response = $this->withHeader('Authorization', 'Bearer ' . $currentToken)
            ->patchJson('/api/v1/profile', [
                'email' => 'doc.after@hospital.local',
                'current_password' => 'SecretPass123!',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'doctor' => [
                    'email' => 'doc.after@hospital.local',
                ],
            ],
        ]);

        $doctor->refresh();
        $this->assertSame('doc.after@hospital.local', $doctor->email);

        // Other tokens are revoked, current token still exists
        $this->assertSame(1, $doctor->tokens()->count());

        // Current token still works
        $responseCurrent = $this->withHeader('Authorization', 'Bearer ' . $currentToken)
            ->getJson('/api/v1/auth/me');
        $responseCurrent->assertStatus(200);

        // Clear in-memory guards so Sanctum re-authenticates from the database for the other token
        auth()->forgetGuards();

        // Other token is revoked and returns 401
        $responseOther = $this->withHeader('Authorization', 'Bearer ' . $otherToken1)
            ->getJson('/api/v1/auth/me');
        $responseOther->assertStatus(401);
    }

    public function test_email_change_with_wrong_current_password_returns_422_not_401(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doc.secure@hospital.local',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $token = $doctor->createToken('device-current')->plainTextToken;
        $otherToken = $doctor->createToken('device-other')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'email' => 'new.email@hospital.local',
                'current_password' => 'WrongPassword!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');

        $doctor->refresh();
        $this->assertSame('doc.secure@hospital.local', $doctor->email);

        // Tokens untouched
        $this->assertSame(2, $doctor->tokens()->count());
    }

    public function test_email_change_without_current_password_returns_422(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doc.secure@hospital.local',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $token = $doctor->createToken('device-current')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'email' => 'new.email@hospital.local',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
    }

    public function test_google_only_doctor_email_change_returns_403_but_name_change_succeeds(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'google-sub-999',
            'email' => 'google.user@hospital.local',
            'password' => null, // Google-only account without password
        ]);

        $token = $doctor->createToken('test-device')->plainTextToken;

        // 1. Email change is rejected with 403 email_change_not_allowed
        $responseEmail = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'email' => 'new.google.user@hospital.local',
            ]);

        $responseEmail->assertStatus(403);
        $responseEmail->assertJson([
            'success' => false,
            'message' => 'Email cannot be changed for accounts that sign in with Google.',
            'error' => [
                'code' => 'email_change_not_allowed',
            ],
        ]);

        // 2. Name or phone change succeeds with 200
        $responseName = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'full_name' => 'Dr. Renamed Google User',
                'phone_number' => '+201011112222',
            ]);

        $responseName->assertStatus(200);
        $doctor->refresh();
        $this->assertSame('Dr. Renamed Google User', $doctor->full_name);
        $this->assertSame('+201011112222', $doctor->phone_number);
    }

    public function test_google_linked_doctor_with_password_can_change_email_and_google_id_remains_unchanged(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'google-sub-linked',
            'email' => 'linked.old@hospital.local',
            'password' => Hash::make('Password123!'),
        ]);

        $token = $doctor->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'email' => 'linked.new@hospital.local',
                'current_password' => 'Password123!',
            ]);

        $response->assertStatus(200);

        $doctor->refresh();
        $this->assertSame('linked.new@hospital.local', $doctor->email);
        $this->assertSame('google-sub-linked', $doctor->google_id);
    }

    public function test_admin_doctor_can_update_profile_and_retains_admin_status(): void
    {
        $admin = Doctor::factory()->admin()->create();
        $token = $admin->createToken('admin-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'full_name' => 'Admin Boss Updated',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'doctor' => [
                    'is_admin' => true,
                ],
            ],
        ]);

        $admin->refresh();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('Admin Boss Updated', $admin->full_name);
    }

    public function test_profile_response_contains_no_sensitive_fields(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'secret-google-sub',
            'password' => Hash::make('SecretPass!'),
        ]);
        $token = $doctor->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', [
                'full_name' => 'Dr. Privacy Test',
            ]);

        $response->assertStatus(200);
        $response->assertDontSee('secret-google-sub');
        $response->assertDontSee('SecretPass!');
        $response->assertDontSee('remember_token');
        $response->assertDontSee('deleted_at');
    }

    public function test_profile_route_is_rate_limited(): void
    {
        $doctor = Doctor::factory()->create();
        $token = $doctor->createToken('test-device')->plainTextToken;

        // 10 requests allowed
        for ($i = 0; $i < 10; $i++) {
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->patchJson('/api/v1/profile', ['full_name' => "Name {$i}"]);
        }

        // 11th request receives 429
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', ['full_name' => 'Name 11']);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'too_many_requests',
            ],
        ]);
    }
}

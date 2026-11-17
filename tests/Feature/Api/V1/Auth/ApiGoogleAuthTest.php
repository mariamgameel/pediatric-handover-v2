<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Contracts\GoogleIdTokenVerifier;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ApiGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_resolves_existing_doctor_by_google_id(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'google-sub-111',
            'email' => 'google.user@hospital.local',
            'last_login_at' => null,
            'is_admin' => false,
        ]);

        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-google-id-token')
            ->andReturn([
                'sub' => 'google-sub-111',
                'email' => 'google.user@hospital.local',
                'email_verified' => true,
                'name' => 'Dr. Google User',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-google-id-token',
            'device_name' => 'Android Tablet',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'token_type',
                'doctor' => ['doctor_id', 'email', 'is_google_linked'],
            ],
        ]);
        $response->assertJson([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'doctor' => [
                    'doctor_id' => $doctor->doctor_id,
                    'email' => 'google.user@hospital.local',
                    'is_google_linked' => true,
                ],
            ],
        ]);

        $doctor->refresh();
        $this->assertNotNull($doctor->last_login_at);
    }

    public function test_google_login_links_existing_doctor_by_verified_email(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => null,
            'email' => 'unlinked@hospital.local',
        ]);

        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'new-google-sub-222',
                'email' => 'unlinked@hospital.local',
                'email_verified' => true,
                'name' => 'Dr. Linking',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'token-to-link',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.doctor.is_google_linked', true);

        $doctor->refresh();
        $this->assertSame('new-google-sub-222', $doctor->google_id);
    }

    public function test_google_login_fails_if_email_is_unverified(): void
    {
        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'sub-unverified',
                'email' => 'unverified@hospital.local',
                'email_verified' => false,
                'name' => 'Dr. Unverified',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'token-unverified',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'unverified_email',
            ],
        ]);
    }

    public function test_google_login_fails_if_doctor_already_linked_to_different_google_id(): void
    {
        Doctor::factory()->create([
            'google_id' => 'original-google-id',
            'email' => 'conflict@hospital.local',
        ]);

        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'intruder-google-id',
                'email' => 'conflict@hospital.local',
                'email_verified' => true,
                'name' => 'Dr. Conflict',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'intruder-token',
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'conflict',
            ],
        ]);
    }

    public function test_google_login_fails_if_doctor_is_soft_deleted(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'deleted-google-sub',
            'email' => 'deleted@hospital.local',
        ]);
        $doctor->delete();

        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'deleted-google-sub',
                'email' => 'deleted@hospital.local',
                'email_verified' => true,
                'name' => 'Dr. Deleted',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'deleted-doc-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
            ],
        ]);
    }

    public function test_google_login_fails_with_invalid_or_expired_token(): void
    {
        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andThrow(new InvalidArgumentException('Google ID token has expired.'));

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'expired-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Google ID token has expired.',
            'error' => [
                'code' => 'invalid_token',
            ],
        ]);
    }

    public function test_new_google_account_without_doctor_id_returns_422_with_doctor_id_required_and_meta(): void
    {
        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'brand-new-google-sub',
                'email' => 'brandnew@hospital.local',
                'email_verified' => true,
                'name' => 'Dr. Newbie',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'new-token',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Hospital Doctor ID is required for registration.',
            'error' => [
                'code' => 'doctor_id_required',
            ],
            'meta' => [
                'email' => 'brandnew@hospital.local',
                'name' => 'Dr. Newbie',
            ],
        ]);
    }

    public function test_new_google_account_with_doctor_id_registers_doctor_and_returns_201(): void
    {
        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'google-reg-sub-555',
                'email' => 'registered.via.google@hospital.local',
                'email_verified' => true,
                'name' => 'Dr. Registered Google',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'google-reg-token',
            'doctor_id' => 'DOC-GOOG-555',
            'device_name' => 'iPad Air',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'token_type',
                'doctor' => ['doctor_id', 'email', 'is_admin', 'is_google_linked'],
            ],
        ]);
        $response->assertJson([
            'success' => true,
            'data' => [
                'doctor' => [
                    'doctor_id' => 'DOC-GOOG-555',
                    'email' => 'registered.via.google@hospital.local',
                    'is_admin' => false,
                    'is_google_linked' => true,
                ],
            ],
        ]);

        $this->assertDatabaseHas('doctors', [
            'doctor_id' => 'DOC-GOOG-555',
            'email' => 'registered.via.google@hospital.local',
            'google_id' => 'google-reg-sub-555',
            'is_admin' => false,
        ]);
    }

    public function test_new_google_account_fails_if_doctor_id_already_taken(): void
    {
        Doctor::factory()->create([
            'doctor_id' => 'DOC-TAKEN',
            'email' => 'taken@hospital.local',
        ]);

        $mockVerifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'google-sub-dup',
                'email' => 'newemail@hospital.local',
                'email_verified' => true,
                'name' => 'Dr. Dup',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'token-dup',
            'doctor_id' => 'DOC-TAKEN',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('doctor_id');
    }

    public function test_google_registration_with_is_admin_in_payload_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'some-token',
            'doctor_id' => 'DOC-ESC',
            'is_admin' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_admin');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\Api\Profile\UpdateProfileRequest;
use App\Models\Doctor;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfileService();
    }

    public function test_update_profile_partial_updates_only_present_fields(): void
    {
        $doctor = Doctor::factory()->create([
            'full_name' => 'Dr. Original Name',
            'email' => 'original@hospital.local',
            'phone_number' => '+1111111111',
        ]);

        // Partial update: only full_name
        $updated = $this->service->updateProfile($doctor, [
            'full_name' => 'Dr. Updated Name',
        ]);

        $this->assertSame('Dr. Updated Name', $updated->full_name);
        $this->assertSame('original@hospital.local', $updated->email);
        $this->assertSame('+1111111111', $updated->phone_number);

        $doctor->refresh();
        $this->assertSame('Dr. Updated Name', $doctor->full_name);
        $this->assertSame('original@hospital.local', $doctor->email);
        $this->assertSame('+1111111111', $doctor->phone_number);
    }

    public function test_update_profile_allows_clearing_phone_number_to_null(): void
    {
        $doctor = Doctor::factory()->create([
            'phone_number' => '+1111111111',
        ]);

        $this->service->updateProfile($doctor, [
            'phone_number' => null,
        ]);

        $doctor->refresh();
        $this->assertNull($doctor->phone_number);
    }

    public function test_update_profile_email_change_with_valid_password(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doc.initial@hospital.local',
            'password' => 'Password123!',
        ]);

        $this->service->updateProfile($doctor, [
            'email' => 'doc.new@hospital.local',
            'current_password' => 'Password123!',
        ]);

        $doctor->refresh();
        $this->assertSame('doc.new@hospital.local', $doctor->email);
    }

    public function test_update_profile_request_validation(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doc1@hospital.local',
            'password' => 'Password123!',
        ]);

        $otherDoctor = Doctor::factory()->create([
            'email' => 'doc2@hospital.local',
        ]);

        $request = new UpdateProfileRequest();
        $request->setUserResolver(fn () => $doctor);
        $rules = $request->rules();

        // 1. Valid partial update with same email passes without current_password
        $validatorSameEmail = Validator::make([
            'full_name' => 'Dr. Updated',
            'email' => 'doc1@hospital.local',
            'phone_number' => '+1234567890',
        ], $rules);
        $this->assertFalse($validatorSameEmail->fails());

        // 2. Duplicate other doctor email fails
        $validatorDuplicate = Validator::make([
            'email' => 'doc2@hospital.local',
            'current_password' => 'Password123!',
        ], $rules);
        $this->assertTrue($validatorDuplicate->fails());
        $this->assertArrayHasKey('email', $validatorDuplicate->errors()->toArray());

        // 3. Prohibited fields fail
        $validatorDisallowed = Validator::make([
            'full_name' => 'Dr. Updated',
            'is_admin' => true,
        ], $rules);
        $this->assertTrue($validatorDisallowed->fails());
        $this->assertArrayHasKey('is_admin', $validatorDisallowed->errors()->toArray());
    }
}

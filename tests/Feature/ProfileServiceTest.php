<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\UpdateProfileRequest;
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

    public function test_update_profile_updates_fields_successfully(): void
    {
        $doctor = Doctor::factory()->create([
            'full_name' => 'Dr. Original Name',
            'email' => 'original@hospital.local',
            'phone_number' => '+1111111111',
        ]);

        $updated = $this->service->updateProfile($doctor, [
            'full_name' => 'Dr. Updated Name',
            'email' => 'updated@hospital.local',
            'phone_number' => '+2222222222',
        ]);

        $this->assertSame('Dr. Updated Name', $updated->full_name);
        $this->assertSame('updated@hospital.local', $updated->email);
        $this->assertSame('+2222222222', $updated->phone_number);

        $doctor->refresh();
        $this->assertSame('Dr. Updated Name', $doctor->full_name);
        $this->assertSame('updated@hospital.local', $doctor->email);
        $this->assertSame('+2222222222', $doctor->phone_number);
    }

    public function test_update_profile_allows_clearing_phone_number_to_null(): void
    {
        $doctor = Doctor::factory()->create([
            'phone_number' => '+1111111111',
        ]);

        $this->service->updateProfile($doctor, [
            'full_name' => $doctor->full_name,
            'email' => $doctor->email,
            'phone_number' => null,
        ]);

        $doctor->refresh();
        $this->assertNull($doctor->phone_number);
    }

    public function test_update_profile_request_validates_required_fields_and_forbids_is_admin(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'doc1@hospital.local',
        ]);

        $otherDoctor = Doctor::factory()->create([
            'email' => 'doc2@hospital.local',
        ]);

        $request = new UpdateProfileRequest();
        $request->setUserResolver(fn () => $doctor);
        $rules = $request->rules();

        // 1. Valid data with same email passes
        $validatorSameEmail = Validator::make([
            'full_name' => 'Dr. Updated',
            'email' => 'doc1@hospital.local',
            'phone_number' => '+1234567890',
        ], $rules);
        $this->assertFalse($validatorSameEmail->fails());

        // 2. Duplicate other doctor email fails
        $validatorDuplicate = Validator::make([
            'full_name' => 'Dr. Updated',
            'email' => 'doc2@hospital.local',
        ], $rules);
        $this->assertTrue($validatorDuplicate->fails());
        $this->assertArrayHasKey('email', $validatorDuplicate->errors()->toArray());

        // 3. Providing is_admin is prohibited
        $validatorIsAdmin = Validator::make([
            'full_name' => 'Dr. Updated',
            'email' => 'doc1@hospital.local',
            'is_admin' => true,
        ], $rules);
        $this->assertTrue($validatorIsAdmin->fails());
        $this->assertArrayHasKey('is_admin', $validatorIsAdmin->errors()->toArray());
    }
}

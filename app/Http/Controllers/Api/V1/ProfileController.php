<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateProfileRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Update doctor profile.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Profile updated successfully.",
     *   "data": {
     *     "doctor": {
     *       "doctor_id": "DOC-10001",
     *       "full_name": "Dr. Sarah Connor",
     *       "email": "sarah@hospital.local",
     *       "phone_number": "+201012345678",
     *       "is_admin": false,
     *       "is_google_linked": false,
     *       "last_login_at": "2026-11-20T18:00:00Z",
     *       "created_at": "2026-11-20T18:00:00Z",
     *       "updated_at": "2026-11-20T18:00:00Z"
     *     }
     *   }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Email cannot be changed for accounts that sign in with Google.",
     *   "error": { "code": "email_change_not_allowed" }
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "Validation failed.",
     *   "error": { "code": "validation_failed" }
     * }
     * @response 429 {
     *   "success": false,
     *   "message": "Too many requests.",
     *   "error": { "code": "too_many_requests" }
     * }
     */
    public function update(UpdateProfileRequest $request, ProfileService $profileService): JsonResponse
    {
        /** @var Doctor $doctor */
        $doctor = $request->user();

        $updatedDoctor = $profileService->updateProfile($doctor, $request->validated());

        return ApiResponse::success([
            'doctor' => new DoctorResource($updatedDoctor),
        ], 'Profile updated successfully.');
    }
}

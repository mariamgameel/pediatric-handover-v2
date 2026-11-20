<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Patients\StorePatientRequest;
use App\Http\Requests\Api\Patients\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function __construct(
        protected PatientService $patientService,
    ) {
    }

    /**
     * Create a new patient record (Admin only).
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Patient created successfully.",
     *   "data": {
     *     "patient": {
     *       "patient_id": "PAT-10001",
     *       "full_name": "Baby John Doe",
     *       "date_of_birth": "2024-01-15",
     *       "gender": "male",
     *       "created_at": "2026-11-20T18:00:00Z",
     *       "updated_at": "2026-11-20T18:00:00Z"
     *     }
     *   }
     * }
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated.",
     *   "error": { "code": "unauthenticated" }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized.",
     *   "error": { "code": "forbidden" }
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
    public function store(StorePatientRequest $request): JsonResponse
    {
        Gate::authorize('create', Patient::class);

        try {
            $patient = $this->patientService->create($request->validated());
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'patient_id' => ['The patient id has already been taken.'],
                ]);
            }

            throw $e;
        }

        return ApiResponse::success([
            'patient' => new PatientResource($patient),
        ], 'Patient created successfully.', 201);
    }

    /**
     * Update an existing patient record (Admin only).
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Patient updated successfully.",
     *   "data": {
     *     "patient": {
     *       "patient_id": "PAT-10001",
     *       "full_name": "Baby John Doe Updated",
     *       "date_of_birth": "2024-01-16",
     *       "gender": "male",
     *       "created_at": "2026-11-20T18:00:00Z",
     *       "updated_at": "2026-11-20T18:00:00Z"
     *     }
     *   }
     * }
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated.",
     *   "error": { "code": "unauthenticated" }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized.",
     *   "error": { "code": "forbidden" }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Resource not found.",
     *   "error": { "code": "not_found" }
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
    public function update(UpdatePatientRequest $request, string $patient_id): JsonResponse
    {
        $patient = Patient::findOrFail($patient_id);

        Gate::authorize('update', $patient);

        $updated = $this->patientService->update($patient, $request->validated());

        return ApiResponse::success([
            'patient' => new PatientResource($updated),
        ], 'Patient updated successfully.');
    }

    /**
     * Delete a patient record (Admin only).
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Patient deleted successfully.",
     *   "data": null
     * }
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated.",
     *   "error": { "code": "unauthenticated" }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized.",
     *   "error": { "code": "forbidden" }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Resource not found.",
     *   "error": { "code": "not_found" }
     * }
     * @response 409 {
     *   "success": false,
     *   "message": "Cannot delete patient because clinical updates exist.",
     *   "error": { "code": "patient_has_clinical_updates" }
     * }
     * @response 429 {
     *   "success": false,
     *   "message": "Too many requests.",
     *   "error": { "code": "too_many_requests" }
     * }
     */
    public function destroy(Request $request, string $patient_id): JsonResponse
    {
        $patient = Patient::findOrFail($patient_id);

        Gate::authorize('delete', $patient);

        $this->patientService->delete($patient);

        return ApiResponse::success(null, 'Patient deleted successfully.');
    }

    /**
     * Determine if the query exception was caused by a unique constraint violation.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = $e->errorInfo[1] ?? null;
        $message = strtolower($e->getMessage());

        if ($driverCode === 1062) {
            return true;
        }

        if ($driverCode === 19 && str_contains($message, 'unique')) {
            return true;
        }

        if ($sqlState === '23000' && (
            str_contains($message, 'duplicate') ||
            str_contains($message, 'unique') ||
            str_contains($message, '1062')
        )) {
            return true;
        }

        return false;
    }
}

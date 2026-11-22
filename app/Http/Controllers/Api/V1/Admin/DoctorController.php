<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ListDoctorsRequest;
use App\Http\Resources\AdminDoctorResource;
use App\Models\Doctor;
use App\Services\AdminAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DoctorController extends Controller
{
    public function __construct(
        protected AdminAccountService $adminAccountService,
    ) {
    }

    /**
     * List and search doctors (Admin only).
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": [
     *     {
     *       "doctor_id": "DOC-10001",
     *       "full_name": "Dr. Sarah Connor",
     *       "email": "sarah@hospital.local",
     *       "phone_number": "+201012345678",
     *       "is_admin": false,
     *       "status": "active",
     *       "has_password": true,
     *       "has_google": false,
     *       "last_login_at": "2026-11-20T18:00:00Z",
     *       "created_at": "2026-11-20T18:00:00Z",
     *       "updated_at": "2026-11-20T18:00:00Z",
     *       "deleted_at": null,
     *       "clinical_updates_count": 5
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 20,
     *     "total": 1,
     *     "last_page": 1
     *   }
     * }
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated.",
     *   "error": { "code": "unauthenticated" }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Administrator access required.",
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
    public function index(ListDoctorsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Doctor::class);

        $paginator = $this->adminAccountService->paginateDoctors($request->validated());

        /** @var list<array<string, mixed>> $items */
        $items = AdminDoctorResource::collection($paginator->items())->resolve();

        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return ApiResponse::success($items, null, 200, $meta);
    }

    /**
     * Get doctor details by doctor_id (Admin only).
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": {
     *     "doctor": {
     *       "doctor_id": "DOC-10001",
     *       "full_name": "Dr. Sarah Connor",
     *       "email": "sarah@hospital.local",
     *       "phone_number": "+201012345678",
     *       "is_admin": false,
     *       "status": "active",
     *       "has_password": true,
     *       "has_google": false,
     *       "last_login_at": "2026-11-20T18:00:00Z",
     *       "created_at": "2026-11-20T18:00:00Z",
     *       "updated_at": "2026-11-20T18:00:00Z",
     *       "deleted_at": null,
     *       "clinical_updates_count": 5
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
     *   "message": "Administrator access required.",
     *   "error": { "code": "forbidden" }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Resource not found.",
     *   "error": { "code": "not_found" }
     * }
     * @response 429 {
     *   "success": false,
     *   "message": "Too many requests.",
     *   "error": { "code": "too_many_requests" }
     * }
     */
    public function show(Request $request, string $doctor_id): JsonResponse
    {
        $doctor = $this->adminAccountService->getDoctor($doctor_id);

        Gate::authorize('view', $doctor);

        return ApiResponse::success([
            'doctor' => new AdminDoctorResource($doctor),
        ]);
    }

    /**
     * Deactivate a doctor account (Admin only).
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Doctor deactivated successfully.",
     *   "data": null
     * }
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated.",
     *   "error": { "code": "unauthenticated" }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You cannot deactivate your own account.",
     *   "error": { "code": "cannot_deactivate_self" }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Resource not found.",
     *   "error": { "code": "not_found" }
     * }
     * @response 409 {
     *   "success": false,
     *   "message": "Doctor is already deactivated.",
     *   "error": { "code": "doctor_already_deactivated" }
     * }
     * @response 429 {
     *   "success": false,
     *   "message": "Too many requests.",
     *   "error": { "code": "too_many_requests" }
     * }
     */
    public function destroy(Request $request, string $doctor_id): JsonResponse
    {
        $actingDoctor = $request->user();
        if ($actingDoctor !== null && $actingDoctor->doctor_id === $doctor_id) {
            return ApiResponse::error('You cannot deactivate your own account.', 'cannot_deactivate_self', 403);
        }

        $doctor = $this->adminAccountService->getDoctor($doctor_id);

        Gate::authorize('delete', $doctor);

        if ($doctor->trashed()) {
            return ApiResponse::error('Doctor is already deactivated.', 'doctor_already_deactivated', 409);
        }

        $this->adminAccountService->deleteDoctor($doctor);

        return ApiResponse::success(null, 'Doctor deactivated successfully.');
    }

    /**
     * Restore a deactivated doctor account (Admin only).
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Doctor restored successfully.",
     *   "data": {
     *     "doctor": {
     *       "doctor_id": "DOC-10001",
     *       "full_name": "Dr. Sarah Connor",
     *       "email": "sarah@hospital.local",
     *       "phone_number": "+201012345678",
     *       "is_admin": false,
     *       "status": "active",
     *       "has_password": true,
     *       "has_google": false,
     *       "last_login_at": "2026-11-20T18:00:00Z",
     *       "created_at": "2026-11-20T18:00:00Z",
     *       "updated_at": "2026-11-20T18:00:00Z",
     *       "deleted_at": null,
     *       "clinical_updates_count": 5
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
     * @response 409 {
     *   "success": false,
     *   "message": "Doctor is not deactivated.",
     *   "error": { "code": "doctor_not_deactivated" }
     * }
     * @response 429 {
     *   "success": false,
     *   "message": "Too many requests.",
     *   "error": { "code": "too_many_requests" }
     * }
     */
    public function restore(Request $request, string $doctor_id): JsonResponse
    {
        $actingDoctor = $request->user();
        if ($actingDoctor !== null && $actingDoctor->doctor_id === $doctor_id) {
            return ApiResponse::error('This action is unauthorized.', 'forbidden', 403);
        }

        $doctor = $this->adminAccountService->getDoctor($doctor_id);

        Gate::authorize('restore', $doctor);

        if (! $doctor->trashed()) {
            return ApiResponse::error('Doctor is not deactivated.', 'doctor_not_deactivated', 409);
        }

        $this->adminAccountService->restoreDoctor($doctor);

        $doctor->refresh();
        $doctor->loadCount('clinicalUpdates');

        return ApiResponse::success([
            'doctor' => new AdminDoctorResource($doctor),
        ], 'Doctor restored successfully.');
    }
}

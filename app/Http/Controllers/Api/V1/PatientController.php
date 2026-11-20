<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Patients\ListPatientsRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    public function __construct(
        protected PatientService $patientService,
    ) {
    }

    /**
     * List and search patients.
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": [
     *     {
     *       "patient_id": "PAT-10001",
     *       "full_name": "Baby John Doe",
     *       "date_of_birth": "2024-01-15",
     *       "gender": "male",
     *       "created_at": "2026-11-20T18:00:00Z",
     *       "updated_at": "2026-11-20T18:00:00Z"
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
    public function index(ListPatientsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Patient::class);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $this->patientService->paginate($filters, $perPage);

        /** @var list<array<string, mixed>> $items */
        $items = PatientResource::collection($paginator->items())->resolve();

        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return ApiResponse::success($items, null, 200, $meta);
    }

    /**
     * Get patient details by patient_id.
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
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
    public function show(Request $request, string $patient_id): JsonResponse
    {
        $patient = Patient::findOrFail($patient_id);

        Gate::authorize('view', $patient);

        return ApiResponse::success([
            'patient' => new PatientResource($patient),
        ]);
    }
}

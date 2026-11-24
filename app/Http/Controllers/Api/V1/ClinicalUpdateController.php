<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClinicalUpdates\ListClinicalUpdatesRequest;
use App\Http\Requests\Api\ClinicalUpdates\StoreClinicalUpdateRequest;
use App\Http\Resources\ClinicalUpdateResource;
use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Services\ClinicalUpdateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClinicalUpdateController extends Controller
{
    public function __construct(
        protected ClinicalUpdateService $clinicalUpdateService,
    ) {
    }

    /**
     * Create a new clinical update for a patient.
     *
     * Clinical updates are strictly append-only. The `content` field contains PLAIN TEXT;
     * clients must NEVER render it as HTML.
     *
     * @header Idempotency-Key string Optional idempotency key (8-64 alphanumeric, underscore, or dash characters).
     * @response 201 {
     *   "success": true,
     *   "message": "Clinical update created successfully.",
     *   "data": {
     *     "update": {
     *       "update_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
     *       "patient_id": "PAT-10001",
     *       "content": "Patient vitals stable. Temperature 37.1 C.",
     *       "author": {
     *         "doctor_id": "DOC-10001",
     *         "full_name": "Dr. Sarah Connor",
     *         "is_deactivated": false
     *       },
     *       "corrects_update_id": null,
     *       "created_at": "2026-11-24T18:00:00Z"
     *     }
     *   }
     * }
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": {
     *     "update": {
     *       "update_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
     *       "patient_id": "PAT-10001",
     *       "content": "Patient vitals stable. Temperature 37.1 C.",
     *       "author": {
     *         "doctor_id": "DOC-10001",
     *         "full_name": "Dr. Sarah Connor",
     *         "is_deactivated": false
     *       },
     *       "corrects_update_id": null,
     *       "created_at": "2026-11-24T18:00:00Z"
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
     *   "message": "The idempotency key has already been used with a different request payload.",
     *   "error": { "code": "idempotency_key_conflict" }
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
    public function store(StoreClinicalUpdateRequest $request, string $patient_id): JsonResponse
    {
        Gate::authorize('create', ClinicalUpdate::class);

        /** @var Doctor $author */
        $author = $request->user();
        $idempotencyKey = $request->header('Idempotency-Key');

        $result = $this->clinicalUpdateService->create(
            $author,
            $patient_id,
            $request->validated(),
            $idempotencyKey !== null ? (string) $idempotencyKey : null
        );

        $update = $result['update'];
        $replayed = $result['replayed'];

        if ($replayed) {
            return ApiResponse::success([
                'update' => new ClinicalUpdateResource($update),
            ], null, 200)->header('Idempotent-Replayed', 'true');
        }

        return ApiResponse::success([
            'update' => new ClinicalUpdateResource($update),
        ], 'Clinical update created successfully.', 201);
    }

    /**
     * List clinical updates for a patient.
     *
     * Ordered newest first (created_at DESC, update_id DESC) using cursor pagination.
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": [
     *     {
     *       "update_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
     *       "patient_id": "PAT-10001",
     *       "content": "Patient vitals stable. Temperature 37.1 C.",
     *       "author": {
     *         "doctor_id": "DOC-10001",
     *         "full_name": "Dr. Sarah Connor",
     *         "is_deactivated": false
     *       },
     *       "corrects_update_id": null,
     *       "created_at": "2026-11-24T18:00:00Z",
     *       "corrections_count": 0
     *     }
     *   ],
     *   "meta": {
     *     "per_page": 20,
     *     "next_cursor": "eyJpZCI6IjAxQVJaM05ERUtUU1Y0UlJGRlE2OUc1RkFWIiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ",
     *     "prev_cursor": null,
     *     "has_more": false
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
    public function index(ListClinicalUpdatesRequest $request, string $patient_id): JsonResponse
    {
        Gate::authorize('viewAny', ClinicalUpdate::class);

        $paginator = $this->clinicalUpdateService->paginateForPatient($patient_id, $request->validated());

        /** @var list<array<string, mixed>> $items */
        $items = ClinicalUpdateResource::collection($paginator->items())->resolve();

        $meta = [
            'per_page' => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
        ];

        return ApiResponse::success($items, null, 200, $meta);
    }

    /**
     * Get a single clinical update by update_id.
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": {
     *     "update": {
     *       "update_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
     *       "patient_id": "PAT-10001",
     *       "content": "Patient vitals stable. Temperature 37.1 C.",
     *       "author": {
     *         "doctor_id": "DOC-10001",
     *         "full_name": "Dr. Sarah Connor",
     *         "is_deactivated": false
     *       },
     *       "corrects_update_id": null,
     *       "created_at": "2026-11-24T18:00:00Z",
     *       "corrections": []
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
    public function show(Request $request, string $update_id): JsonResponse
    {
        $update = $this->clinicalUpdateService->getUpdate($update_id);

        Gate::authorize('view', $update);

        return ApiResponse::success([
            'update' => new ClinicalUpdateResource($update),
        ]);
    }
}

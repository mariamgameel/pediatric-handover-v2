<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClinicalUpdateService
{
    /**
     * Create a new clinical update or replay an idempotent request.
     *
     * @param array<string, mixed> $data
     * @return array{update: ClinicalUpdate, replayed: bool}
     *
     * @throws ValidationException
     * @throws IdempotencyKeyConflictException
     */
    public function create(Doctor $author, string $patientId, array $data, ?string $idempotencyKey = null): array
    {
        return DB::transaction(function () use ($author, $patientId, $data, $idempotencyKey): array {
            $patient = Patient::where('patient_id', $patientId)->firstOrFail();

            $content = (string) $data['content'];
            $correctsUpdateId = ! empty($data['corrects_update_id']) ? (string) $data['corrects_update_id'] : null;

            if ($idempotencyKey !== null) {
                $existing = ClinicalUpdate::where('doctor_id', $author->doctor_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    $samePatient = $existing->patient_id === $patient->patient_id;
                    $sameContent = $existing->content === $content;
                    $sameCorrects = $existing->corrects_update_id === $correctsUpdateId;

                    if ($samePatient && $sameContent && $sameCorrects) {
                        return ['update' => $existing, 'replayed' => true];
                    }

                    throw new IdempotencyKeyConflictException();
                }
            }

            if ($correctsUpdateId !== null) {
                $original = ClinicalUpdate::where('update_id', $correctsUpdateId)->first();

                if ($original === null) {
                    throw ValidationException::withMessages([
                        'corrects_update_id' => ['The referenced clinical update does not exist.'],
                    ]);
                }

                if ($original->patient_id !== $patient->patient_id) {
                    throw ValidationException::withMessages([
                        'corrects_update_id' => ['The referenced update does not belong to this patient.'],
                    ]);
                }

                if ($original->corrects_update_id !== null) {
                    throw ValidationException::withMessages([
                        'corrects_update_id' => ['A correction cannot correct another correction. Please correct the original update.'],
                    ]);
                }

                Gate::forUser($author)->authorize('correct', $original);
            }

            try {
                $update = new ClinicalUpdate();
                $update->patient_id = $patient->patient_id;
                $update->doctor_id = $author->doctor_id;
                $update->content = $content;
                $update->corrects_update_id = $correctsUpdateId;
                $update->idempotency_key = $idempotencyKey;
                $update->created_at = now();
                $update->updated_at = now();
                $update->save();

                return ['update' => $update, 'replayed' => false];
            } catch (QueryException $e) {
                if ($idempotencyKey !== null && $this->isUniqueConstraintViolation($e)) {
                    $existing = ClinicalUpdate::where('doctor_id', $author->doctor_id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing !== null) {
                        $samePatient = $existing->patient_id === $patient->patient_id;
                        $sameContent = $existing->content === $content;
                        $sameCorrects = $existing->corrects_update_id === $correctsUpdateId;

                        if ($samePatient && $sameContent && $sameCorrects) {
                            return ['update' => $existing, 'replayed' => true];
                        }

                        throw new IdempotencyKeyConflictException();
                    }
                }

                throw $e;
            }
        });
    }

    /**
     * Get cursor-paginated updates for a patient.
     *
     * @param array<string, mixed> $filters
     * @return CursorPaginator<ClinicalUpdate>
     */
    public function paginateForPatient(string $patientId, array $filters): CursorPaginator
    {
        $patient = Patient::where('patient_id', $patientId)->firstOrFail();

        $query = ClinicalUpdate::query()
            ->where('patient_id', $patient->patient_id)
            ->with(['doctor' => fn ($q) => $q->withTrashed()])
            ->withCount('corrections');

        if (! empty($filters['since'])) {
            $since = Carbon::parse((string) $filters['since']);
            $query->where('created_at', '>', $since);
        }

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', (string) $filters['doctor_id']);
        }

        $query->orderBy('created_at', 'desc')->orderBy('update_id', 'desc');

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->cursorPaginate($perPage);
    }

    /**
     * Get a single clinical update by its ULID.
     */
    public function getUpdate(string $updateId): ClinicalUpdate
    {
        return ClinicalUpdate::with([
            'doctor' => fn ($q) => $q->withTrashed(),
            'corrections' => fn ($q) => $q->with(['doctor' => fn ($dq) => $dq->withTrashed()])->orderBy('created_at', 'asc'),
        ])->where('update_id', $updateId)->firstOrFail();
    }

    /**
     * Determine if a QueryException was caused by a unique constraint violation.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = $e->errorInfo[1] ?? null;
        $message = strtolower($e->getMessage());

        return $driverCode === 1062
            || ($driverCode === 19 && str_contains($message, 'unique'))
            || ($sqlState === '23000' && (str_contains($message, 'unique') || str_contains($message, 'duplicate') || str_contains($message, '1062')));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminAccountService
{
    /**
     * Get a paginated list of doctors filtered by status and search query.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<Doctor>
     */
    public function paginateDoctors(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $status = (string) ($filters['status'] ?? 'active');

        $query = match ($status) {
            'deleted', 'deactivated' => Doctor::onlyTrashed(),
            'all' => Doctor::withTrashed(),
            default => Doctor::query(),
        };

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $escapedTerm = addcslashes($term, '%_\\');
            $driver = $query->getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                $query->where(function ($subQuery) use ($escapedTerm): void {
                    $subQuery->whereRaw("doctor_id LIKE ? ESCAPE '\\'", ["%{$escapedTerm}%"])
                        ->orWhereRaw("full_name LIKE ? ESCAPE '\\'", ["%{$escapedTerm}%"])
                        ->orWhereRaw("email LIKE ? ESCAPE '\\'", ["%{$escapedTerm}%"]);
                });
            } else {
                $query->where(function ($subQuery) use ($escapedTerm): void {
                    $subQuery->where('doctor_id', 'LIKE', "%{$escapedTerm}%")
                        ->orWhere('full_name', 'LIKE', "%{$escapedTerm}%")
                        ->orWhere('email', 'LIKE', "%{$escapedTerm}%");
                });
            }
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy('full_name', $direction)->orderBy('doctor_id', 'asc');

        $query->withCount('clinicalUpdates');

        $limit = isset($filters['per_page']) ? (int) $filters['per_page'] : $perPage;
        $page = isset($filters['page']) ? (int) $filters['page'] : null;

        return $query->paginate($limit, ['*'], 'page', $page)->withQueryString();
    }

    /**
     * Find a doctor by ID, including soft-deleted ones.
     */
    public function getDoctor(string $doctorId): Doctor
    {
        return Doctor::withTrashed()
            ->withCount('clinicalUpdates')
            ->where('doctor_id', $doctorId)
            ->firstOrFail();
    }

    /**
     * Get a paginated list of clinical updates authored by the given doctor.
     *
     * @return LengthAwarePaginator<ClinicalUpdate>
     */
    public function getDoctorClinicalUpdates(Doctor $doctor, int $perPage = 15): LengthAwarePaginator
    {
        return $doctor->clinicalUpdates()
            ->with('patient')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Soft delete a doctor account.
     */
    public function deleteDoctor(Doctor $doctor): void
    {
        $doctor->delete();
    }

    /**
     * Restore a soft-deleted doctor account.
     */
    public function restoreDoctor(Doctor $doctor): void
    {
        $doctor->restore();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CannotDeleteAdminException;
use App\Models\Admin;
use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminAccountService
{
    /**
     * Get a paginated list of doctors filtered by status and search query.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<Doctor>
     */
    public function paginateDoctors(array $filters): LengthAwarePaginator
    {
        $status = (string) ($filters['status'] ?? 'active');

        $query = match ($status) {
            'deleted' => Doctor::onlyTrashed(),
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

        $query->orderBy('full_name', 'asc');

        return $query->paginate(20)->withQueryString();
    }

    /**
     * Find a doctor by ID, including soft-deleted ones.
     */
    public function getDoctor(string $doctorId): Doctor
    {
        return Doctor::withTrashed()->where('doctor_id', $doctorId)->firstOrFail();
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

    /**
     * List all administrators, including soft-deleted ones.
     *
     * @return Collection<int, Admin>
     */
    public function listAdmins(): Collection
    {
        return Admin::withTrashed()->orderBy('admin_id', 'asc')->get();
    }

    /**
     * Soft delete another administrator account safely.
     *
     * @throws CannotDeleteAdminException
     */
    public function deleteAdmin(Admin $currentAdmin, Admin $targetAdmin): void
    {
        if ($currentAdmin->admin_id === $targetAdmin->admin_id) {
            throw new CannotDeleteAdminException('An administrator cannot delete their own account.');
        }

        DB::transaction(function () use ($targetAdmin): void {
            // Lock active admins to prevent concurrent mutual-deletion races
            $activeAdminCount = Admin::whereNull('deleted_at')->lockForUpdate()->count();

            if ($activeAdminCount <= 1) {
                throw new CannotDeleteAdminException('Cannot delete the last remaining active administrator.');
            }

            $adminToDelete = Admin::where('admin_id', $targetAdmin->admin_id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($adminToDelete === null) {
                throw new CannotDeleteAdminException('Administrator is already deleted or not found.');
            }

            $adminToDelete->delete();
        });
    }
}

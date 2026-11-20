<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CannotDeletePatientWithUpdatesException;
use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class PatientService
{
    /**
     * Get a paginated list of patients filtered and sorted.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<Patient>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Patient::query();

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $escapedTerm = addcslashes($term, '%_\\');
            $driver = $query->getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                $query->where(function ($subQuery) use ($escapedTerm): void {
                    $subQuery->whereRaw("patient_id LIKE ? ESCAPE '\\'", ["%{$escapedTerm}%"])
                        ->orWhereRaw("full_name LIKE ? ESCAPE '\\'", ["%{$escapedTerm}%"]);
                });
            } else {
                $query->where(function ($subQuery) use ($escapedTerm): void {
                    $subQuery->where('patient_id', 'LIKE', "%{$escapedTerm}%")
                        ->orWhere('full_name', 'LIKE', "%{$escapedTerm}%");
                });
            }
        }

        if (!empty($filters['gender'])) {
            $gender = trim((string) $filters['gender']);
            /** @var list<string> $allowedGenders */
            $allowedGenders = config('hospital.genders', ['male', 'female']);

            if (in_array($gender, $allowedGenders, true)) {
                $query->where('gender', $gender);
            }
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy('full_name', $direction)->orderBy('patient_id', 'asc');

        $limit = isset($filters['per_page']) ? (int) $filters['per_page'] : $perPage;
        $page = isset($filters['page']) ? (int) $filters['page'] : null;

        return $query->paginate($limit, ['*'], 'page', $page)->withQueryString();
    }

    /**
     * Create a new patient record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Patient
    {
        return Patient::create([
            'patient_id' => trim((string) $data['patient_id']),
            'full_name' => trim((string) $data['full_name']),
            'date_of_birth' => trim((string) $data['date_of_birth']),
            'gender' => trim((string) $data['gender']),
        ]);
    }

    /**
     * Update an existing patient record.
     *
     * @param array<string, mixed> $data
     */
    public function update(Patient $patient, array $data): Patient
    {
        $patient->update([
            'full_name' => trim((string) $data['full_name']),
            'date_of_birth' => trim((string) $data['date_of_birth']),
            'gender' => trim((string) $data['gender']),
        ]);

        return $patient;
    }

    /**
     * Delete a patient record, preventing deletion if clinical updates exist.
     *
     * @throws CannotDeletePatientWithUpdatesException
     */
    public function delete(Patient $patient): void
    {
        try {
            if ($patient->clinicalUpdates()->exists()) {
                throw new CannotDeletePatientWithUpdatesException();
            }

            $patient->delete();
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw new CannotDeletePatientWithUpdatesException(
                    'Cannot delete patient because clinical updates exist for this patient.',
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    /**
     * Determine if the query exception was caused by a foreign key constraint violation.
     */
    private function isForeignKeyViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = $e->errorInfo[1] ?? null;
        $message = strtolower($e->getMessage());

        // MySQL error 1451: Cannot delete or update a parent row: a foreign key constraint fails
        if ($driverCode === 1451) {
            return true;
        }

        // SQLite error 19: foreign key constraint failed
        if ($driverCode === 19 && str_contains($message, 'foreign key')) {
            return true;
        }

        // SQLSTATE 23000 FK checks if message contains foreign key or 1451
        if ($sqlState === '23000' && (
            str_contains($message, '1451') ||
            str_contains($message, 'foreign key')
        )) {
            return true;
        }

        return false;
    }
}

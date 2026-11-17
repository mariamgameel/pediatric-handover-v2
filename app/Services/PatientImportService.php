<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use SplFileObject;

class PatientImportService
{
    /**
     * Import patients from a CSV file.
     *
     * @param string $filePath Absolute or relative path to CSV file
     * @param bool $dryRun When true, validates and previews counts without writing to database
     */
    public function import(string $filePath, bool $dryRun = false): PatientImportResult
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("CSV file not found or is not readable: {$filePath}");
        }

        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = null;
        $rowNumber = 0;
        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        $rules = [
            'patient_id' => [
                'required',
                'string',
                'max:' . config('hospital.patient_id_max_length', 64),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d'],
            'gender' => [
                'required',
                'string',
                Rule::in(config('hospital.genders', ['male', 'female'])),
            ],
        ];

        foreach ($file as $row) {
            $rowNumber++;

            if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            if ($headers === null) {
                // Strip UTF-8 BOM from the first header column if present
                $bom = pack('H*', 'EFBBBF');
                $row[0] = preg_replace("/^{$bom}/", '', (string) $row[0]);

                $headers = array_map(static fn ($header): string => trim((string) $header), $row);
                continue;
            }

            if (count($row) !== count($headers)) {
                $errors[$rowNumber] = ["Row column count ({count($row)}) does not match header column count ({count($headers)})."];
                $skippedCount++;
                continue;
            }

            $trimmedRow = array_map(static fn ($value): string => trim((string) $value), $row);
            $rowData = array_combine($headers, $trimmedRow);

            $validator = Validator::make($rowData, $rules);

            if ($validator->fails()) {
                $errors[$rowNumber] = $validator->errors()->all();
                $skippedCount++;
                continue;
            }

            $existingPatient = Patient::where('patient_id', $rowData['patient_id'])->first();

            if ($dryRun) {
                if ($existingPatient !== null) {
                    $updatedCount++;
                } else {
                    $importedCount++;
                }
                continue;
            }

            if ($existingPatient !== null) {
                $existingPatient->update([
                    'full_name' => $rowData['full_name'],
                    'date_of_birth' => $rowData['date_of_birth'],
                    'gender' => $rowData['gender'],
                ]);
                $updatedCount++;
            } else {
                Patient::create([
                    'patient_id' => $rowData['patient_id'],
                    'full_name' => $rowData['full_name'],
                    'date_of_birth' => $rowData['date_of_birth'],
                    'gender' => $rowData['gender'],
                ]);
                $importedCount++;
            }
        }

        $totalRows = $importedCount + $updatedCount + $skippedCount;

        return new PatientImportResult(
            totalRows: $totalRows,
            importedCount: $importedCount,
            updatedCount: $updatedCount,
            skippedCount: $skippedCount,
            errors: $errors,
            isDryRun: $dryRun,
        );
    }
}

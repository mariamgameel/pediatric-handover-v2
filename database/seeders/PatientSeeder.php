<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\PatientImportService;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function __construct(
        protected PatientImportService $importService,
    ) {
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/patients.csv');

        if (!file_exists($csvPath)) {
            $this->command?->info('No patients.csv found, skipping');
            return;
        }

        $result = $this->importService->import($csvPath, dryRun: false);

        if ($this->command !== null) {
            $this->command->info(sprintf(
                'Patients seeded: %d imported, %d updated, %d skipped.',
                $result->importedCount,
                $result->updatedCount,
                $result->skippedCount
            ));

            if (!empty($result->errors)) {
                foreach ($result->errors as $rowNumber => $rowErrors) {
                    $this->command->warn(sprintf(
                        'Row %d skipped: %s',
                        $rowNumber,
                        implode('; ', $rowErrors)
                    ));
                }
            }
        }
    }
}

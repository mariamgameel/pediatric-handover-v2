<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PatientImportService;
use Illuminate\Console\Command;
use Throwable;

class PatientsImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patients:import {file : Path to CSV file} {--dry-run : Validate and preview import without saving changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import patients from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle(PatientImportService $importService): int
    {
        $filePath = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        if (!file_exists($filePath)) {
            $this->error("File does not exist: {$filePath}");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No database records will be created or modified.');
        }

        try {
            $result = $importService->import($filePath, $dryRun);
        } catch (Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed Rows', $result->totalRows],
                ['New Patients Imported', $result->importedCount],
                ['Existing Patients Updated', $result->updatedCount],
                ['Skipped / Invalid Rows', $result->skippedCount],
            ]
        );

        if (!empty($result->errors)) {
            $this->newLine();
            $this->warn('Validation Errors:');
            $errorRows = [];
            foreach ($result->errors as $rowNumber => $rowErrors) {
                $errorRows[] = [$rowNumber, implode(', ', $rowErrors)];
            }
            $this->table(['Row #', 'Error Details'], $errorRows);
        }

        if ($result->isSuccess()) {
            $this->info('Patient import completed successfully with 0 errors.');
            return self::SUCCESS;
        }

        $this->warn("Patient import completed with {$result->skippedCount} skipped row(s).");
        return self::SUCCESS;
    }
}

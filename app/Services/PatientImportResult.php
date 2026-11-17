<?php

declare(strict_types=1);

namespace App\Services;

class PatientImportResult
{
    /**
     * @param array<int, list<string>> $errors
     */
    public function __construct(
        public readonly int $totalRows = 0,
        public readonly int $importedCount = 0,
        public readonly int $updatedCount = 0,
        public readonly int $skippedCount = 0,
        public readonly array $errors = [],
        public readonly bool $isDryRun = false,
    ) {
    }

    public function isSuccess(): bool
    {
        return empty($this->errors);
    }
}

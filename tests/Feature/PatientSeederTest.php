<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\PatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PatientSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_seeder_with_no_patients_csv_inserts_nothing_and_succeeds(): void
    {
        $realCsvPath = database_path('seeders/data/patients.csv');

        // Ensure real patients.csv does not exist during this test
        if (File::exists($realCsvPath)) {
            File::delete($realCsvPath);
        }

        $this->seed(PatientSeeder::class);

        $this->assertDatabaseCount('patients', 0);
    }
}

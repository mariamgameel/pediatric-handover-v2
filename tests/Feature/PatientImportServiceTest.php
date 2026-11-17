<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Patient;
use App\Services\PatientImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PatientImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pedi_test_' . uniqid();
        File::makeDirectory($this->tempDir, 0755, true, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_imports_valid_rows(): void
    {
        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'valid.csv';
        $content = "patient_id,full_name,date_of_birth,gender\n" .
            "PAT-101,John Doe,2021-03-15,male\n" .
            "PAT-102,Jane Doe,2022-07-20,female\n";
        file_put_contents($csvPath, $content);

        $service = app(PatientImportService::class);
        $result = $service->import($csvPath);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2, $result->importedCount);
        $this->assertSame(0, $result->updatedCount);
        $this->assertSame(0, $result->skippedCount);

        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-101',
            'full_name' => 'John Doe',
            'gender' => 'male',
        ]);
        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-102',
            'full_name' => 'Jane Doe',
            'gender' => 'female',
        ]);
    }

    public function test_reports_invalid_rows_with_row_numbers(): void
    {
        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'invalid.csv';
        $content = "patient_id,full_name,date_of_birth,gender\n" .
            "PAT-201,Valid Patient,2020-01-01,male\n" .
            ",Missing ID,2020-01-01,male\n" .                       // Row 3: missing patient_id
            "PAT-203,Invalid Date,15-03-2020,female\n" .            // Row 4: invalid date format
            "PAT-204,Invalid Gender,2021-05-10,unknown_gender\n";   // Row 5: invalid gender
        file_put_contents($csvPath, $content);

        $service = app(PatientImportService::class);
        $result = $service->import($csvPath);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(1, $result->importedCount);
        $this->assertSame(3, $result->skippedCount);

        $this->assertArrayHasKey(3, $result->errors);
        $this->assertArrayHasKey(4, $result->errors);
        $this->assertArrayHasKey(5, $result->errors);

        $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-201']);
        $this->assertDatabaseMissing('patients', ['full_name' => 'Missing ID']);
    }

    public function test_handles_utf8_bom_file(): void
    {
        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'bom.csv';
        $bom = pack('H*', 'EFBBBF');
        $content = $bom . "patient_id,full_name,date_of_birth,gender\n" .
            "PAT-BOM-01,BOM Patient,2019-11-11,male\n";
        file_put_contents($csvPath, $content);

        $service = app(PatientImportService::class);
        $result = $service->import($csvPath);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $result->importedCount);
        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-BOM-01',
            'full_name' => 'BOM Patient',
        ]);
    }

    public function test_preserves_arabic_names(): void
    {
        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'arabic.csv';
        $arabicName = 'يوسف محمد عبد الرحمن';
        $content = "patient_id,full_name,date_of_birth,gender\n" .
            "PAT-AR-01,{$arabicName},2023-01-15,male\n";
        file_put_contents($csvPath, $content);

        $service = app(PatientImportService::class);
        $result = $service->import($csvPath);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $result->importedCount);

        $patient = Patient::find('PAT-AR-01');
        $this->assertNotNull($patient);
        $this->assertSame($arabicName, $patient->full_name);
    }

    public function test_upserts_duplicate_patient_id(): void
    {
        Patient::factory()->create([
            'patient_id' => 'PAT-DUP-01',
            'full_name' => 'Old Name',
            'gender' => 'male',
        ]);

        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'upsert.csv';
        $content = "patient_id,full_name,date_of_birth,gender\n" .
            "PAT-DUP-01,Updated Name,2020-06-01,female\n";
        file_put_contents($csvPath, $content);

        $service = app(PatientImportService::class);
        $result = $service->import($csvPath);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(0, $result->importedCount);
        $this->assertSame(1, $result->updatedCount);

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-DUP-01',
            'full_name' => 'Updated Name',
            'gender' => 'female',
        ]);
    }

    public function test_dry_run_mode_does_not_persist(): void
    {
        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'dry_run.csv';
        $content = "patient_id,full_name,date_of_birth,gender\n" .
            "PAT-DRY-01,Dry Run Patient,2022-02-02,male\n";
        file_put_contents($csvPath, $content);

        $service = app(PatientImportService::class);
        $result = $service->import($csvPath, dryRun: true);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->isDryRun);
        $this->assertSame(1, $result->importedCount);
        $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-DRY-01']);
    }
}

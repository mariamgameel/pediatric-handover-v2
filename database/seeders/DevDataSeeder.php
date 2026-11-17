<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class DevDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DevDataSeeder is restricted to non-production environments.');
            return;
        }

        // Import sample patients in dev environment if available
        $sampleCsvPath = database_path('seeders/data/patients.sample.csv');
        if (file_exists($sampleCsvPath)) {
            app(\App\Services\PatientImportService::class)->import($sampleCsvPath);
        }

        // Create 5 doctors (including 1 with Google auth profile)
        $doctors = Doctor::factory()->count(4)->create();
        $doctors->push(Doctor::factory()->googleOnly()->create());

        // Create 10 patients
        $patients = Patient::factory()->count(10)->create();

        // Create 12 initial clinical updates
        $updates = collect();
        for ($i = 0; $i < 12; $i++) {
            $patient = $patients->random();
            $doctor = $doctors->random();

            $update = ClinicalUpdate::factory()->create([
                'patient_id' => $patient->patient_id,
                'doctor_id' => $doctor->doctor_id,
                'content' => sprintf('Clinical handover notes for %s. Patient is stable.', $patient->full_name),
                'corrects_update_id' => null,
            ]);

            $updates->push($update);
        }

        // Create 3 corrections chained to existing updates for the same patient
        for ($i = 0; $i < 3; $i++) {
            /** @var ClinicalUpdate $original */
            $original = $updates->random();
            $correctingDoctor = $doctors->random();

            ClinicalUpdate::factory()->create([
                'patient_id' => $original->patient_id,
                'doctor_id' => $correctingDoctor->doctor_id,
                'content' => sprintf('Correction to update %s: Dosage amended.', $original->update_id),
                'corrects_update_id' => $original->update_id,
            ]);
        }
    }
}

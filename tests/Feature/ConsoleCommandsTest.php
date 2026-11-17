<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_command_interactively_creates_admin(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Full Name', 'Command Administrator')
            ->expectsQuestion('Email Address', 'cmd.admin@hospital.local')
            ->expectsQuestion('Password', 'adminsecret123')
            ->expectsQuestion('Confirm Password', 'adminsecret123')
            ->expectsOutputToContain('created successfully')
            ->assertSuccessful();

        $this->assertDatabaseHas('admins', [
            'email' => 'cmd.admin@hospital.local',
            'full_name' => 'Command Administrator',
        ]);
    }

    public function test_patients_import_command_executes_successfully(): void
    {
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cli_import_' . uniqid() . '.csv';
        $content = "patient_id,full_name,date_of_birth,gender\n" .
            "PAT-CLI-01,CLI Patient,2022-08-10,female\n";
        file_put_contents($tempPath, $content);

        // Dry-run mode
        $this->artisan('patients:import', ['file' => $tempPath, '--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN mode')
            ->assertSuccessful();

        $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-CLI-01']);

        // Real import mode
        $this->artisan('patients:import', ['file' => $tempPath])
            ->expectsOutputToContain('Patient import completed successfully')
            ->assertSuccessful();

        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-CLI-01',
            'full_name' => 'CLI Patient',
        ]);

        File::delete($tempPath);
    }
}

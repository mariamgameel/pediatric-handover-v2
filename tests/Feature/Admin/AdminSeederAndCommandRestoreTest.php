<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederAndCommandRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_restores_and_promotes_soft_deleted_doctor_with_same_id_and_email(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-RESTORE-01',
            'email' => 'admin.restored@hospital.local',
            'full_name' => 'Old Trashed Doctor',
            'password' => 'old_password_hash',
            'is_admin' => false,
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-RESTORE-01']);

        config([
            'hospital.admin_doctor_id' => 'DOC-RESTORE-01',
            'hospital.admin_name' => 'Restored Hospital Admin',
            'hospital.admin_email' => 'admin.restored@hospital.local',
            'hospital.admin_password' => 'NewSecurePassword123!',
        ]);

        $this->artisan('db:seed', ['--class' => AdminSeeder::class])
            ->expectsOutputToContain('Restored soft-deleted doctor')
            ->assertSuccessful();

        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-RESTORE-01']);
        $this->assertDatabaseCount('doctors', 1);

        $doctor->refresh();
        $this->assertSame('Restored Hospital Admin', $doctor->full_name);
        $this->assertTrue($doctor->isAdmin());
        $this->assertTrue(Hash::check('NewSecurePassword123!', $doctor->password));
    }

    public function test_admin_seeder_aborts_and_changes_nothing_when_doctor_id_or_email_conflict(): void
    {
        // Existing doctor with DOC-TAKEN-01 and taken@hospital.local
        $existingDoctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-TAKEN-01',
            'email' => 'taken@hospital.local',
            'full_name' => 'Existing Doctor',
            'is_admin' => false,
        ]);

        // Attempting to seed admin with same doctor_id but DIFFERENT email
        config([
            'hospital.admin_doctor_id' => 'DOC-TAKEN-01',
            'hospital.admin_name' => 'Intruder Admin',
            'hospital.admin_email' => 'different@hospital.local',
            'hospital.admin_password' => 'NewSecurePassword123!',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Account takeover prevented');

        app(AdminSeeder::class)->run();

        // Ensure existing doctor was NOT modified or promoted
        $existingDoctor->refresh();
        $this->assertSame('Existing Doctor', $existingDoctor->full_name);
        $this->assertSame('taken@hospital.local', $existingDoctor->email);
        $this->assertFalse($existingDoctor->isAdmin());
    }

    public function test_admin_create_command_restores_and_promotes_soft_deleted_doctor(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-CMD-RESTORE-01',
            'email' => 'cmd.restore@hospital.local',
            'full_name' => 'Former Doctor',
            'password' => 'former_password_hash',
            'is_admin' => false,
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-CMD-RESTORE-01']);

        $this->artisan('admin:create')
            ->expectsQuestion('Hospital Doctor ID', 'DOC-CMD-RESTORE-01')
            ->expectsQuestion('Full Name', 'Revived Admin')
            ->expectsQuestion('Email Address', 'cmd.restore@hospital.local')
            ->expectsQuestion('Password', 'revivedSecret123!')
            ->expectsQuestion('Confirm Password', 'revivedSecret123!')
            ->expectsOutputToContain('Restored soft-deleted doctor')
            ->assertSuccessful();

        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-CMD-RESTORE-01']);
        $this->assertDatabaseCount('doctors', 1);

        $doctor->refresh();
        $this->assertSame('Revived Admin', $doctor->full_name);
        $this->assertTrue($doctor->isAdmin());
        $this->assertTrue(Hash::check('revivedSecret123!', $doctor->password));
    }

    public function test_admin_create_command_aborts_on_account_conflict(): void
    {
        $existingDoctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-CONFLICT-01',
            'email' => 'original@hospital.local',
            'full_name' => 'Original Doctor',
            'is_admin' => false,
        ]);

        $this->artisan('admin:create')
            ->expectsQuestion('Hospital Doctor ID', 'DOC-CONFLICT-01')
            ->expectsQuestion('Full Name', 'Attacker Admin')
            ->expectsQuestion('Email Address', 'hijacked@hospital.local')
            ->expectsQuestion('Password', 'hijackSecret123!')
            ->expectsQuestion('Confirm Password', 'hijackSecret123!')
            ->expectsOutputToContain('Account takeover prevented')
            ->assertFailed();

        $existingDoctor->refresh();
        $this->assertSame('Original Doctor', $existingDoctor->full_name);
        $this->assertFalse($existingDoctor->isAdmin());
    }
}

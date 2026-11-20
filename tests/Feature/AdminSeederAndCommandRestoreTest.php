<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        Doctor::factory()->create([
            'doctor_id' => 'DOC-CONFLICT-ID',
            'email' => 'other@hospital.local',
            'full_name' => 'Existing Doctor',
            'is_admin' => false,
        ]);

        config([
            'hospital.admin_doctor_id' => 'DOC-CONFLICT-ID',
            'hospital.admin_email' => 'admin.new@hospital.local',
            'hospital.admin_name' => 'Hospital Admin',
            'hospital.admin_password' => 'Password123!',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Admin seeding aborted');

        app(AdminSeeder::class)->run();
    }

    public function test_admin_create_command_promotes_existing_active_doctor(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-PROMOTE-01',
            'email' => 'existing.doctor@hospital.local',
            'full_name' => 'Dr. Ordinary',
            'is_admin' => false,
        ]);

        $this->artisan('admin:create')
            ->expectsQuestion('Hospital Doctor ID', 'DOC-PROMOTE-01')
            ->expectsQuestion('Full Name', 'Dr. Promoted Admin')
            ->expectsQuestion('Email Address', 'existing.doctor@hospital.local')
            ->expectsQuestion('Password', 'adminsecret123')
            ->expectsQuestion('Confirm Password', 'adminsecret123')
            ->expectsOutputToContain('Promoted existing doctor')
            ->assertSuccessful();

        $doctor->refresh();
        $this->assertTrue($doctor->isAdmin());
        $this->assertSame('Dr. Promoted Admin', $doctor->full_name);
        $this->assertTrue(Hash::check('adminsecret123', $doctor->password));
    }

    public function test_admin_create_command_restores_and_promotes_soft_deleted_doctor(): void
    {
        $doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-CMD-RESTORE',
            'email' => 'restored.cmd@hospital.local',
            'full_name' => 'Old Trashed Doctor',
            'is_admin' => false,
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-CMD-RESTORE']);

        $this->artisan('admin:create')
            ->expectsQuestion('Hospital Doctor ID', 'DOC-CMD-RESTORE')
            ->expectsQuestion('Full Name', 'Dr. Restored Leader')
            ->expectsQuestion('Email Address', 'restored.cmd@hospital.local')
            ->expectsQuestion('Password', 'adminsecret123')
            ->expectsQuestion('Confirm Password', 'adminsecret123')
            ->expectsOutputToContain('Restored soft-deleted doctor')
            ->assertSuccessful();

        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-CMD-RESTORE']);
        $doctor->refresh();
        $this->assertTrue($doctor->isAdmin());
        $this->assertSame('Dr. Restored Leader', $doctor->full_name);
        $this->assertTrue(Hash::check('adminsecret123', $doctor->password));
    }
}

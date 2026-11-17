<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederAndCommandRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_restores_soft_deleted_admin_with_same_email(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin.restored@hospital.local',
            'full_name' => 'Old Trashed Admin',
            'password' => 'old_password_hash',
        ]);
        $admin->delete();
        $this->assertSoftDeleted('admins', ['email' => 'admin.restored@hospital.local']);

        config([
            'hospital.admin_name' => 'Restored Hospital Admin',
            'hospital.admin_email' => 'admin.restored@hospital.local',
            'hospital.admin_password' => 'NewSecurePassword123!',
        ]);

        $this->artisan('db:seed', ['--class' => AdminSeeder::class])
            ->expectsOutputToContain('Restored soft-deleted admin')
            ->assertSuccessful();

        $this->assertNotSoftDeleted('admins', ['email' => 'admin.restored@hospital.local']);
        $this->assertDatabaseCount('admins', 1);

        $admin->refresh();
        $this->assertSame('Restored Hospital Admin', $admin->full_name);
        $this->assertTrue(Hash::check('NewSecurePassword123!', $admin->password));
    }

    public function test_admin_create_command_restores_soft_deleted_admin_with_same_email(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'cmd.restore@hospital.local',
            'full_name' => 'Former Admin',
            'password' => 'former_password_hash',
        ]);
        $admin->delete();
        $this->assertSoftDeleted('admins', ['email' => 'cmd.restore@hospital.local']);

        $this->artisan('admin:create')
            ->expectsQuestion('Full Name', 'Revived Admin')
            ->expectsQuestion('Email Address', 'cmd.restore@hospital.local')
            ->expectsQuestion('Password', 'revivedSecret123!')
            ->expectsQuestion('Confirm Password', 'revivedSecret123!')
            ->expectsOutputToContain('Restored soft-deleted admin')
            ->assertSuccessful();

        $this->assertNotSoftDeleted('admins', ['email' => 'cmd.restore@hospital.local']);
        $this->assertDatabaseCount('admins', 1);

        $admin->refresh();
        $this->assertSame('Revived Admin', $admin->full_name);
        $this->assertTrue(Hash::check('revivedSecret123!', $admin->password));
    }
}

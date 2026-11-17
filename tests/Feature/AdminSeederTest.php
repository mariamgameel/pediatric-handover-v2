<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_is_idempotent(): void
    {
        config([
            'hospital.admin_name' => 'First Run Admin',
            'hospital.admin_email' => 'admin.idempotent@hospital.local',
            'hospital.admin_password' => 'InitialSecret123!',
        ]);

        app(AdminSeeder::class)->run();

        $this->assertDatabaseCount('doctors', 1);
        $this->assertDatabaseHas('doctors', [
            'doctor_id' => 'DOC-ADMIN-01',
            'email' => 'admin.idempotent@hospital.local',
            'full_name' => 'First Run Admin',
            'is_admin' => 1,
        ]);

        // Second run with updated name and password
        config([
            'hospital.admin_name' => 'Second Run Admin',
            'hospital.admin_password' => 'UpdatedSecret123!',
        ]);

        app(AdminSeeder::class)->run();

        $this->assertDatabaseCount('doctors', 1);
        $this->assertDatabaseHas('doctors', [
            'doctor_id' => 'DOC-ADMIN-01',
            'email' => 'admin.idempotent@hospital.local',
            'full_name' => 'Second Run Admin',
            'is_admin' => 1,
        ]);
    }

    public function test_admin_seeder_refuses_empty_password_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config([
            'hospital.admin_name' => 'Hospital Admin',
            'hospital.admin_email' => 'admin.prod@hospital.local',
            'hospital.admin_password' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD cannot be empty in production');

        app(AdminSeeder::class)->run();
    }

    public function test_admin_seeder_refuses_default_password_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config([
            'hospital.admin_name' => 'Hospital Admin',
            'hospital.admin_email' => 'admin.prod@hospital.local',
            'hospital.admin_password' => 'password',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD cannot be a known default password in production');

        app(AdminSeeder::class)->run();
    }

    public function test_admin_seeder_succeeds_in_production_with_secure_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config([
            'hospital.admin_name' => 'Hospital Admin',
            'hospital.admin_email' => 'admin.prod@hospital.local',
            'hospital.admin_password' => 'SuperSecureHospitalProd2026!',
        ]);

        app(AdminSeeder::class)->run();

        $this->assertDatabaseHas('doctors', [
            'email' => 'admin.prod@hospital.local',
            'full_name' => 'Hospital Admin',
            'is_admin' => 1,
        ]);
    }
}

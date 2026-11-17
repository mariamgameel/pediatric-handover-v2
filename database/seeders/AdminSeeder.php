<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Known default passwords prohibited in production environments.
     *
     * @var list<string>
     */
    protected const INSECURE_DEFAULT_PASSWORDS = [
        'password',
        'admin',
        'admin123',
        'secret',
        '123456',
        '12345678',
        'root',
        'changeme',
        'pediatric',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = config('hospital.admin_name') !== null
            ? (string) config('hospital.admin_name')
            : (string) env('ADMIN_NAME', 'Hospital Admin');

        $email = config('hospital.admin_email') !== null
            ? (string) config('hospital.admin_email')
            : (string) env('ADMIN_EMAIL', 'admin@hospital.local');

        $password = config('hospital.admin_password') !== null
            ? (string) config('hospital.admin_password')
            : (string) env('ADMIN_PASSWORD', '');

        if (app()->isProduction()) {
            if ($password === '') {
                throw new RuntimeException('Admin seeding failed: ADMIN_PASSWORD cannot be empty in production.');
            }

            if (in_array(strtolower($password), self::INSECURE_DEFAULT_PASSWORDS, true)) {
                throw new RuntimeException('Admin seeding failed: ADMIN_PASSWORD cannot be a known default password in production.');
            }
        }

        // Use fallback password for non-production environments if unset
        if ($password === '' && !app()->isProduction()) {
            $password = 'password';
        }

        Admin::updateOrCreate(
            ['email' => $email],
            [
                'full_name' => $name,
                'password' => $password,
            ]
        );
    }
}

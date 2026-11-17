<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class AdminCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new hospital system administrator account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = (string) $this->ask('Full Name');
        $email = (string) $this->ask('Email Address');
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm Password');

        $validator = Validator::make([
            'full_name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8', 'same:password_confirmation'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $admin = Admin::create([
            'full_name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Admin '{$admin->full_name}' ({$admin->email}) created successfully with ID {$admin->admin_id}.");

        return self::SUCCESS;
    }
}

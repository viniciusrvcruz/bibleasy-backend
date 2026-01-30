<?php

namespace App\Console\Commands;

use App\Enums\Auth\AdminAuthProvider;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email : The email address for the admin}';

    protected $description = 'Create an admin user (development only)';

    public function handle(): int
    {
        // Only run in development environments
        if (app()->isProduction()) {
            $this->error('This command can only be run in development environments.');
            $this->warn('Skipping admin creation.');

            return Command::FAILURE;
        }

        $email = $this->argument('email');

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email format: {$email}");

            return Command::FAILURE;
        }

        // Check if admin already exists
        if (Admin::where('email', $email)->exists()) {
            $this->warn("Admin with email {$email} already exists.");

            return Command::FAILURE;
        }

        $admin = Admin::create([
            'name' => 'Admin User',
            'email' => $email,
            'auth_provider' => AdminAuthProvider::GOOGLE->value,
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        $this->info("Admin created successfully with email: {$email}");
        $this->info("Authentication Token:");
        $this->line($token);

        return Command::SUCCESS;
    }
}

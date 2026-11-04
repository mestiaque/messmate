<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\BaseDataSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Installation wizard. Safe to re-run: migrations and base data are idempotent
 * and an existing admin account is never overwritten.
 */
class InstallMessMate extends Command
{
    protected $signature = 'messmate:install
    {--admin-name= : Platform admin name}
    {--admin-email= : Platform admin email}
    {--admin-password= : Platform admin password (prompted when omitted)}
    {--demo : Also load demo organizations (never in production)}';

    protected $description = 'Validate the environment, migrate, seed base data and create the platform admin';

    public function handle(): int
    {
        $this->info('Step 1/4 — checking environment');
        if ($this->call('messmate:check') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Step 2/4 — running migrations');
        $this->call('migrate', ['--force' => true]);

        $this->info('Step 3/4 — seeding roles, permissions and plans');
        $this->call('db:seed', ['--class' => BaseDataSeeder::class, '--force' => true]);

        $this->info('Step 4/4 — platform admin');
        if (! $this->createAdmin()) {
            return self::FAILURE;
        }

        if ($this->option('demo')) {
            if (app()->environment('production')) {
                $this->warn('Demo data is not loaded in production.');
            } elseif (User::query()->where('email', 'owner@messmate.test')->exists()) {
                $this->warn('Demo data is already loaded.');
            } else {
                $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
            }
        }

        $this->newLine();
        $this->info('MessMate is installed. Remember to start a queue worker and the scheduler (see docs/DEPLOYMENT.md).');

        return self::SUCCESS;
    }

    private function createAdmin(): bool
    {
        $email = $this->option('admin-email') ?? $this->ask('Admin email');

        if ($existing = User::query()->where('email', $email)->first()) {
            $existing->forceFill(['is_platform_admin' => true])->save();
            $this->line("  {$email} already exists; made sure it is a platform admin (password unchanged).");

            return true;
        }

        $data = [
            'name' => $this->option('admin-name') ?? $this->ask('Admin name', 'Platform Admin'),
            'email' => $email,
            'password' => $this->option('admin-password') ?? $this->secret('Admin password (min 8 characters)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return false;
        }

        $user = User::query()->create($data);
        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();
        $this->line("  Created platform admin {$email}.");

        return true;
    }
}

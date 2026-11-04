<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BaseDataSeeder::class);

        // Never create a platform admin with a known password outside local/testing.
        if (! app()->environment('local', 'testing') && ! env('PLATFORM_ADMIN_PASSWORD')) {
            $this->command?->warn('PLATFORM_ADMIN_PASSWORD is not set; skipping platform admin. Use php artisan messmate:install.');

            return;
        }

        User::query()->firstOrCreate(
            ['email' => env('PLATFORM_ADMIN_EMAIL', 'admin@messmate.test')],
            ['name' => 'Platform Admin', 'password' => env('PLATFORM_ADMIN_PASSWORD', 'password'), 'is_platform_admin' => true],
        )->forceFill(['is_platform_admin' => true])->save();

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}

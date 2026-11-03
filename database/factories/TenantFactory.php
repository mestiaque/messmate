<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->companyEmail(),
            'status' => 'active',
            'default_currency' => 'BDT',
            'default_timezone' => 'Asia/Dhaka',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            $tenant->users()->syncWithoutDetaching([$tenant->owner_id => ['status' => 'active', 'joined_at' => now()]]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}

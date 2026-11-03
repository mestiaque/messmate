<?php

namespace Database\Factories;

use App\Models\Mess;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Mess>
 */
class MessFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->streetName().' Mess';

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'default_period_mode' => 'calendar',
            'default_meal_tracking_mode' => 'individual',
            'status' => 'active',
        ];
    }
}

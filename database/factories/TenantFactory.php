<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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

            // Unlimited by default so tests not about billing are not constrained by plan limits.
            Subscription::query()->firstOrCreate(['tenant_id' => $tenant->id], [
                'plan_id' => SubscriptionPlan::query()->where('slug', 'enterprise')->value('id'),
                'status' => 'active', 'billing_cycle' => 'monthly', 'starts_at' => now(),
            ]);
        });
    }

    /**
     * Put the tenant on a specific plan and status (for billing tests).
     */
    public function onPlan(string $slug, string $status = 'active', array $attributes = []): static
    {
        return $this->afterCreating(function (Tenant $tenant) use ($slug, $status, $attributes) {
            Subscription::query()->where('tenant_id', $tenant->id)->update($attributes + [
                'plan_id' => SubscriptionPlan::query()->where('slug', $slug)->value('id'),
                'status' => $status,
            ]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}

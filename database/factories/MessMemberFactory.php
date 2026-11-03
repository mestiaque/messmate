<?php

namespace Database\Factories;

use App\Models\Mess;
use App\Models\MessMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessMember>
 */
class MessMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'tenant_id' => fn (array $attributes) => Mess::find($attributes['mess_id'])->tenant_id,
            'user_id' => User::factory(),
            'member_code' => 'M'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('+88017########'),
            'joined_at' => now()->startOfMonth()->toDateString(),
            'membership_status' => 'active',
        ];
    }

    public function withoutAccount(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }

    /**
     * Attach a system mess role (by slug) after creation.
     */
    public function withRole(string $slug): static
    {
        return $this->afterCreating(function (MessMember $member) use ($slug) {
            $role = Role::query()->whereNull('tenant_id')->where('scope', 'mess')->where('slug', $slug)->firstOrFail();
            $member->roles()->attach($role->id, ['mess_id' => $member->mess_id]);

            if ($member->user_id) {
                $member->mess->tenant->users()->syncWithoutDetaching([$member->user_id => ['status' => 'active', 'joined_at' => now()]]);
            }
        });
    }
}

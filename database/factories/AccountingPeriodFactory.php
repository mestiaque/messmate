<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'mess_id' => Mess::factory(),
            'tenant_id' => fn (array $attributes) => Mess::find($attributes['mess_id'])->tenant_id,
            'name' => $start->format('F Y'),
            'period_type' => 'calendar',
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => 'open',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

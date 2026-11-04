<?php

namespace Tests;

use App\Models\AccountingPeriod;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\User;
use App\Services\Meals\MealTypeService;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed reference data (roles, plans) once for the whole run; RefreshDatabase only migrates
     * on the first test, so per-class seeding would be skipped elsewhere.
     */
    protected $seed = true;

    protected $seeder = BaseDataSeeder::class;

    /**
     * Create an active member of the mess with the given system role.
     */
    protected function memberOf(Mess $mess, string $role, ?User $user = null): MessMember
    {
        return MessMember::factory()
            ->for($mess)
            ->state(['tenant_id' => $mess->tenant_id, 'user_id' => ($user ?? User::factory()->create())->id])
            ->withRole($role)
            ->create();
    }

    /**
     * A mess with the default meal types (Breakfast 0.5, Lunch 1, Dinner 1)
     * and an open period covering the current month.
     *
     * @return array{0: Mess, 1: AccountingPeriod}
     */
    protected function messWithOpenPeriod(?string $start = null, ?string $end = null): array
    {
        $mess = Mess::factory()->create();
        app(MealTypeService::class)->createDefaults($mess);

        $period = AccountingPeriod::factory()->for($mess)->state(array_filter([
            'tenant_id' => $mess->tenant_id,
            'start_date' => $start,
            'end_date' => $end,
        ]))->create();

        return [$mess, $period];
    }

    protected function mealTypeIds(Mess $mess): array
    {
        return $mess->mealTypes()->orderBy('sort_order')->pluck('id', 'code')->all();
    }
}

<?php

namespace Database\Seeders;

use App\Enums\PeriodMode;
use App\Enums\PeriodStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\BazarService;
use App\Services\Finance\DepositService;
use App\Services\Finance\ExpenseService;
use App\Services\Meals\MealEntryService;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Tenancy\MembershipService;
use App\Services\Tenancy\MessService;
use App\Services\Tenancy\TenantService;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development. All demo accounts use the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(TenantService $tenants, MessService $messes, MembershipService $memberships, AccountingPeriodService $periods, MealEntryService $meals, BazarService $bazar, ExpenseService $expenses, DepositService $deposits): void
    {
        $owner = User::factory()->create(['name' => 'Demo Owner', 'email' => 'owner@messmate.test']);
        $manager = User::factory()->create(['name' => 'Demo Manager', 'email' => 'manager@messmate.test']);
        $member = User::factory()->create(['name' => 'Demo Member', 'email' => 'member@messmate.test']);

        $tenant = $tenants->create($owner, ['name' => 'Demo Organization', 'default_currency' => 'BDT', 'default_timezone' => 'Asia/Dhaka']);

        $base = ['currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'default_period_mode' => 'calendar', 'default_meal_tracking_mode' => 'individual'];
        $north = $messes->create($tenant, $owner, ['name' => 'North Hall Mess'] + $base);
        $south = $messes->create($tenant, $owner, ['name' => 'South Hall Mess', 'default_meal_tracking_mode' => 'daily_total'] + $base);

        $roleId = fn ($mess, $slug) => Role::query()->assignableInMess($mess)->where('slug', $slug)->value('id');

        // Same user, different roles in different messes.
        $memberships->addUser($north, $manager, [$roleId($north, Role::MANAGER)], $owner);
        $memberships->addUser($south, $manager, [$roleId($south, Role::MEMBER)], $owner);
        $memberships->addUser($north, $member, [$roleId($north, Role::MEMBER)], $owner);
        $memberships->createWithoutAccount($north, ['full_name' => 'Rahim (cook)', 'joined_at' => now()->startOfMonth()->toDateString()], [$roleId($north, Role::MEMBER)], $owner);

        // Current month open in North Hall, with this month's meals so far.
        $period = $periods->create($north, $owner, PeriodMode::Calendar, now()->format('Y-m'), null, null);
        $periods->transition($period, PeriodStatus::Open, $owner);

        $types = $north->mealTypes()->active()->pluck('id');
        foreach ($north->members()->get() as $m) {
            $m->update(['joined_at' => now()->startOfMonth()->toDateString()]);
            for ($day = now()->startOfMonth(); $day->lte(now()); $day->addDay()) {
                $quantities = $types->mapWithKeys(fn ($id) => [$id => random_int(0, 4) ? 1 : 0])->all();
                $meals->saveIndividual($north, $m, $day->toDateString(), $quantities, $owner, approve: $day->lt(now()->subDays(2)));
            }
        }

        // Finance: deposits from everyone, a few bazar runs, rent and electricity.
        $cats = $north->bazarCategories()->pluck('id', 'name');
        foreach ($north->members()->get() as $i => $m) {
            $d = $deposits->record($north, ['member_id' => $m->id, 'amount' => '6000', 'payment_method' => $i % 2 ? 'mobile_wallet' : 'cash', 'paid_at' => now()->startOfMonth()->toDateString(), 'transaction_reference' => $i % 2 ? 'BK'.(1000 + $i) : null], $owner);
            $deposits->approve($d, $owner);
        }

        $buyer = $north->members()->where('user_id', $manager->id)->first();
        $run = $bazar->save($north, ['purchase_date' => now()->startOfMonth()->toDateString(), 'purchased_by' => $buyer->id, 'entry_mode' => 'itemized', 'payment_method' => 'cash', 'description' => 'Weekly bazar', 'discount' => '20', 'items' => [
            ['item_name' => 'Miniket rice', 'category_id' => $cats['Rice'], 'quantity' => '25', 'unit' => 'kg', 'unit_price' => '68'],
            ['item_name' => 'Rui fish', 'category_id' => $cats['Fish'], 'quantity' => '3', 'unit' => 'kg', 'unit_price' => '320'],
            ['item_name' => 'Vim bar', 'category_id' => $cats['Cleaning supplies'], 'quantity' => '4', 'unit' => 'pcs', 'unit_price' => '35'],
        ]], $owner);
        $bazar->approve($run, $owner);
        $bazar->save($north, ['purchase_date' => now()->toDateString(), 'purchased_by' => $buyer->id, 'entry_mode' => 'total', 'category_id' => $cats['Vegetables'], 'amount' => '640', 'payment_method' => 'member_paid', 'description' => 'Vegetables'], $owner);

        $rent = $north->expenseCategories()->where('name', 'Rent')->value('id');
        $expenses->approve($expenses->save($north, ['category_id' => $rent, 'title' => 'House rent', 'amount' => '12000', 'expense_date' => now()->startOfMonth()->toDateString(), 'allocation_method' => 'equal', 'payment_method' => 'mobile_wallet'], $owner), $owner);
    }
}

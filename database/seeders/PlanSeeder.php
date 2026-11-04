<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Idempotent starter catalogue; platform admins can edit plans afterwards.
 */
class PlanSeeder extends Seeder
{
    public const FEATURES = ['reports_export' => 'Excel/CSV export', 'self_service_meals' => 'Member self-service meals'];

    public function run(): void
    {
        $plans = [
            ['slug' => 'free', 'name' => 'Free', 'monthly_price' => 0, 'yearly_price' => 0, 'max_messes' => 1, 'max_members_per_mess' => 8, 'max_users' => 10, 'trial_days' => 0, 'features' => [], 'sort_order' => 1,
                'description' => 'One small mess with the essentials.'],
            ['slug' => 'basic', 'name' => 'Basic', 'monthly_price' => 299, 'yearly_price' => 2990, 'max_messes' => 2, 'max_members_per_mess' => 25, 'max_users' => 40, 'trial_days' => 14, 'features' => ['reports_export', 'self_service_meals'], 'sort_order' => 2,
                'description' => 'For a typical mess or two.'],
            ['slug' => 'pro', 'name' => 'Pro', 'monthly_price' => 699, 'yearly_price' => 6990, 'max_messes' => 10, 'max_members_per_mess' => 60, 'max_users' => 300, 'trial_days' => 14, 'features' => ['reports_export', 'self_service_meals'], 'sort_order' => 3,
                'description' => 'For hostels and organizations running several messes.'],
            ['slug' => 'enterprise', 'name' => 'Enterprise', 'monthly_price' => 1999, 'yearly_price' => 19990, 'max_messes' => null, 'max_members_per_mess' => null, 'max_users' => null, 'trial_days' => 0, 'features' => ['reports_export', 'self_service_meals'], 'sort_order' => 4,
                'description' => 'No limits.'],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->firstOrCreate(['slug' => $plan['slug']], $plan + ['currency' => 'BDT']);
        }
    }
}

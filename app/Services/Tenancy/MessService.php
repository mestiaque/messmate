<?php

namespace App\Services\Tenancy;

use App\Enums\MessStatus;
use App\Models\Mess;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\SubscriptionService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Meals\MealTypeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessService
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly MealTypeService $mealTypes,
        private readonly FinanceSetupService $finance,
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Tenant $tenant, User $creator, array $data, bool $joinAsAdmin = true): Mess
    {
        return DB::transaction(function () use ($tenant, $creator, $data, $joinAsAdmin) {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->first();
            $this->subscriptions->assertCanAdd($tenant, 'messes');

            $mess = new Mess($data);
            $mess->tenant_id = $tenant->id;
            $mess->slug = $this->uniqueSlug($tenant, $data['name']);
            $mess->status = MessStatus::Active;
            $mess->currency ??= $tenant->default_currency;
            $mess->timezone ??= $tenant->default_timezone;
            $mess->created_by = $creator->id;
            $mess->save();

            $this->mealTypes->createDefaults($mess);
            $this->finance->ensureDefaults($mess);

            if ($joinAsAdmin) {
                $adminRole = Role::query()->assignableInMess($mess)->where('slug', Role::MESS_ADMIN)->firstOrFail();
                $this->memberships->addUser($mess, $creator, [$adminRole->id], $creator);
            }

            $this->audit->log('mess.created', $mess, new: $mess->only(['name', 'slug']));

            return $mess;
        });
    }

    public function update(Mess $mess, array $data): Mess
    {
        $old = $mess->only(array_keys($data));
        $mess->update($data);
        $this->audit->log('mess.updated', $mess, $old, $mess->only(array_keys($data)));

        return $mess;
    }

    private function uniqueSlug(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name) ?: 'mess';
        $slug = $base;

        while (Mess::withTrashed()->where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}

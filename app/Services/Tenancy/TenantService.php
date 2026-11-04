<?php

namespace App\Services\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function create(User $owner, array $data): Tenant
    {
        return DB::transaction(function () use ($owner, $data) {
            $tenant = new Tenant($data);
            $tenant->owner_id = $owner->id;
            $tenant->slug = $this->uniqueSlug($data['name']);
            $tenant->status = TenantStatus::Active;
            $tenant->save();

            $this->attachUser($tenant, $owner);
            $this->subscriptions->start($tenant, $owner);
            $this->audit->log('tenant.created', $tenant, new: $tenant->only(['name', 'slug']), tenantId: $tenant->id);

            return $tenant;
        });
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $old = $tenant->only(array_keys($data));
        $tenant->update($data);
        $this->audit->log('tenant.updated', $tenant, $old, $tenant->only(array_keys($data)), tenantId: $tenant->id);

        return $tenant;
    }

    public function setStatus(Tenant $tenant, TenantStatus $status): void
    {
        $old = $tenant->status;
        $tenant->status = $status;
        $tenant->save();

        $this->audit->log('tenant.status_changed', $tenant, ['status' => $old->value], ['status' => $status->value], tenantId: $tenant->id);
    }

    public function attachUser(Tenant $tenant, User $user): void
    {
        $tenant->users()->syncWithoutDetaching([
            $user->id => ['status' => 'active', 'joined_at' => now()],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}

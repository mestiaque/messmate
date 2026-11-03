<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->isActive() && $user->belongsToTenant($tenant);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->isActive() && $tenant->owner_id === $user->id;
    }

    public function createMess(User $user, Tenant $tenant): bool
    {
        return $this->update($user, $tenant);
    }
}

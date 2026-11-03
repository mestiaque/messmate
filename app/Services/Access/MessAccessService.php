<?php

namespace App\Services\Access;

use App\Models\Mess;
use App\Models\User;
use App\Support\Tenancy\Permissions;

/**
 * Answers "may this user do X in this mess?". Tenant owners hold every mess
 * permission in their tenant; everyone else needs an active membership whose
 * roles grant the permission. Results are memoised per request.
 */
class MessAccessService
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    public function allows(User $user, Mess $mess, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user, $mess), true);
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user, Mess $mess): array
    {
        $key = $user->id.':'.$mess->id;

        return $this->cache[$key] ??= $this->resolve($user, $mess);
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    private function resolve(User $user, Mess $mess): array
    {
        if (! $user->isActive()) {
            return [];
        }

        $tenant = $mess->tenant;

        if (! $tenant->isActive()) {
            return [];
        }

        if ($tenant->owner_id === $user->id) {
            return array_keys(Permissions::all());
        }

        $member = $user->activeMembershipIn($mess);

        return $member ? $member->permissionNames() : [];
    }
}

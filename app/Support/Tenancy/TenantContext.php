<?php

namespace App\Support\Tenancy;

use App\Models\Mess;
use App\Models\MessMember;
use App\Models\Tenant;
use LogicException;

/**
 * Holds the tenant and mess resolved for the current request or job.
 * Registered as a scoped singleton so it is reset between requests and queued jobs.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private ?Mess $mess = null;

    private ?MessMember $member = null;

    public function setTenant(Tenant $tenant): void
    {
        if ($this->mess && $this->mess->tenant_id !== $tenant->id) {
            $this->mess = null;
            $this->member = null;
        }

        $this->tenant = $tenant;
    }

    public function setMess(Mess $mess, ?MessMember $member = null): void
    {
        if (! $this->tenant || $this->tenant->id !== $mess->tenant_id) {
            throw new LogicException('Mess does not belong to the current tenant.');
        }

        $this->mess = $mess;
        $this->member = $member;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function mess(): ?Mess
    {
        return $this->mess;
    }

    public function member(): ?MessMember
    {
        return $this->member;
    }

    public function requireTenant(): Tenant
    {
        return $this->tenant ?? throw new LogicException('No tenant in context.');
    }

    public function requireMess(): Mess
    {
        return $this->mess ?? throw new LogicException('No mess in context.');
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->mess = null;
        $this->member = null;
    }

    /**
     * Cache key namespaced by the current tenant (and mess when present),
     * so cached values can never be served across tenants.
     */
    public function cacheKey(string $key): string
    {
        $prefix = 'tenant:'.$this->requireTenant()->id;

        if ($this->mess) {
            $prefix .= ':mess:'.$this->mess->id;
        }

        return $prefix.':'.$key;
    }
}

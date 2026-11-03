<?php

namespace App\Models;

use App\Enums\RoleScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'name', 'slug', 'guard_name', 'scope'])]
class Role extends Model
{
    public const MESS_ADMIN = 'mess-admin';
    public const MANAGER = 'manager';
    public const ACCOUNTANT = 'accountant';
    public const BAZAR_MANAGER = 'bazar-manager';
    public const MEMBER = 'member';

    protected function casts(): array
    {
        return ['scope' => RoleScope::class];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Mess roles usable inside a tenant: system roles plus the tenant's own.
     */
    public function scopeAssignableInMess($query, Mess $mess)
    {
        return $query->where('scope', RoleScope::Mess)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $mess->tenant_id));
    }
}

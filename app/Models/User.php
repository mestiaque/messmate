<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'profile_photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_platform_admin' => 'boolean',
        ];
    }

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot(['status', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MessMember::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        if ($tenant->owner_id === $this->id) {
            return true;
        }

        return $this->tenants()
            ->whereKey($tenant->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Active tenants the user can enter, with the messes they can see in each.
     */
    public function accessibleTenants()
    {
        return Tenant::query()
            ->where('status', TenantStatus::Active)
            ->where(function ($query) {
                $query->where('owner_id', $this->id)
                    ->orWhereHas('users', fn ($q) => $q->whereKey($this->id)->where('tenant_users.status', 'active'));
            })
            ->orderBy('name')
            ->get();
    }

    public function activeMembershipIn(Mess $mess): ?MessMember
    {
        return $this->memberships()
            ->where('mess_id', $mess->id)
            ->where('membership_status', MembershipStatus::Active)
            ->first();
    }
}

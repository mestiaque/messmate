<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\MessMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['full_name', 'phone', 'email', 'room_number', 'joined_at', 'notes'])]
class MessMember extends Model
{
    /** @use HasFactory<MessMemberFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'left_at' => 'date',
            'membership_status' => MembershipStatus::class,
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'mess_member_roles', 'member_id')
            ->withPivot(['mess_id', 'assigned_by'])
            ->withTimestamps();
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'member_id');
    }

    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class, 'member_id');
    }

    /**
     * Whether the member was part of the mess on the given date, judged by the
     * effective membership dates rather than the current status alone.
     */
    public function isEffectiveOn(\DateTimeInterface|string $date): bool
    {
        if (in_array($this->membership_status, [MembershipStatus::Invited, MembershipStatus::Suspended, MembershipStatus::Inactive], true)) {
            return false;
        }

        $date = Carbon::parse($date)->toDateString();

        return $date >= $this->joined_at->toDateString()
            && ($this->left_at === null || $date <= $this->left_at->toDateString());
    }

    /**
     * Members whose membership dates cover the given date.
     */
    public function scopeEffectiveOn($query, \DateTimeInterface|string $date)
    {
        $date = Carbon::parse($date)->toDateString();

        return $query->whereIn('membership_status', [MembershipStatus::Active, MembershipStatus::Left])
            ->whereDate('joined_at', '<=', $date)
            ->where(fn ($q) => $q->whereNull('left_at')->orWhereDate('left_at', '>=', $date));
    }

    public function isActive(): bool
    {
        return $this->membership_status === MembershipStatus::Active;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return Permission::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $this->roles()->select('roles.id')))
            ->pluck('name')
            ->all();
    }
}

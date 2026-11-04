<?php

namespace App\Services\Tenancy;

use App\Enums\MembershipStatus;
use App\Models\Mess;
use App\Models\MessInvitation;
use App\Models\MessMember;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\MessAccessService;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly MessAccessService $access,
        private readonly AuditLogger $audit,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Add a registered user to a mess as an active member with the given roles.
     *
     * @param  list<int>  $roleIds
     */
    public function addUser(Mess $mess, User $user, array $roleIds, User $actor, array $attributes = []): MessMember
    {
        return DB::transaction(function () use ($mess, $user, $roleIds, $actor, $attributes) {
            // Serialise member-code generation and duplicate checks per mess.
            Mess::query()->whereKey($mess->id)->lockForUpdate()->first();

            if ($user->activeMembershipIn($mess)) {
                throw ValidationException::withMessages(['user' => 'This user is already an active member of this mess.']);
            }

            $this->assertPlanAllows($mess, $user);

            $member = new MessMember([
                'full_name' => $attributes['full_name'] ?? $user->name,
                'phone' => $attributes['phone'] ?? $user->phone,
                'email' => $attributes['email'] ?? $user->email,
                'room_number' => $attributes['room_number'] ?? null,
                'joined_at' => $attributes['joined_at'] ?? now()->toDateString(),
            ]);
            $member->tenant_id = $mess->tenant_id;
            $member->mess_id = $mess->id;
            $member->user_id = $user->id;
            $member->member_code = $this->nextMemberCode($mess);
            $member->membership_status = MembershipStatus::Active;
            $member->created_by = $actor->id;
            $member->save();

            $this->tenants->attachUser($mess->tenant, $user);
            $this->syncRoles($member, $roleIds, $actor, audit: false);
            $this->audit->log('member.added', $member, new: ['user_id' => $user->id, 'roles' => $roleIds]);

            return $member;
        });
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function syncRoles(MessMember $member, array $roleIds, User $actor, bool $audit = true): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        $valid = Role::query()->assignableInMess($member->mess)->whereIn('id', $roleIds)->pluck('id')->all();

        if (count($valid) !== count($roleIds)) {
            throw ValidationException::withMessages(['roles' => 'One or more roles cannot be assigned in this mess.']);
        }

        $old = $member->roles()->pluck('roles.id')->all();

        $member->roles()->sync(collect($valid)->mapWithKeys(fn ($id) => [
            $id => ['mess_id' => $member->mess_id, 'assigned_by' => $actor->id],
        ])->all());

        $this->access->forget();

        if ($audit) {
            $this->audit->log('member.roles_synced', $member, ['roles' => $old], ['roles' => $valid]);
        }
    }

    /**
     * A member without a login account (e.g. a cook or someone without a phone).
     *
     * @param  list<int>  $roleIds
     */
    public function createWithoutAccount(Mess $mess, array $attributes, array $roleIds, User $actor): MessMember
    {
        return DB::transaction(function () use ($mess, $attributes, $roleIds, $actor) {
            Mess::query()->whereKey($mess->id)->lockForUpdate()->first();
            $this->assertPlanAllows($mess);

            $member = new MessMember($attributes);
            $member->tenant_id = $mess->tenant_id;
            $member->mess_id = $mess->id;
            $member->member_code = $this->nextMemberCode($mess);
            $member->membership_status = MembershipStatus::Active;
            $member->created_by = $actor->id;
            $member->save();

            $this->syncRoles($member, $roleIds, $actor, audit: false);
            $this->audit->log('member.created', $member, new: $member->only(['full_name', 'member_code', 'joined_at']));

            return $member;
        });
    }

    public function update(MessMember $member, array $attributes): MessMember
    {
        if (isset($attributes['joined_at']) && $member->mealEntries()->whereDate('meal_date', '<', $attributes['joined_at'])->where('status', '!=', 'voided')->exists()) {
            throw ValidationException::withMessages(['joined_at' => 'Meals are recorded before this joining date. Void them first.']);
        }

        $old = $member->only(array_keys($attributes));
        $member->update($attributes);
        $this->audit->log('member.updated', $member, $old, $member->only(array_keys($attributes)));

        return $member;
    }

    /**
     * Change membership status. Leaving needs an effective left date; meals after
     * that date must be voided first so history stays consistent.
     */
    public function changeStatus(MessMember $member, MembershipStatus $status, ?string $leftAt, User $actor): MessMember
    {
        return DB::transaction(function () use ($member, $status, $leftAt) {
            $member = MessMember::query()->lockForUpdate()->findOrFail($member->id);
            $old = ['status' => $member->membership_status->value, 'left_at' => $member->left_at?->toDateString()];

            if ($member->membership_status === MembershipStatus::Left) {
                throw ValidationException::withMessages(['status' => 'This member has left. Add them again as a new membership to rejoin.']);
            }

            if ($status === MembershipStatus::Left) {
                if (! $leftAt) {
                    throw ValidationException::withMessages(['left_at' => 'Enter the date the member left.']);
                }

                if (Carbon::parse($leftAt)->lt($member->joined_at)) {
                    throw ValidationException::withMessages(['left_at' => 'The leaving date cannot be before the joining date.']);
                }

                if ($member->mealEntries()->whereDate('meal_date', '>', $leftAt)->where('status', '!=', 'voided')->exists()) {
                    throw ValidationException::withMessages(['left_at' => 'Meals are recorded after this date. Void them first.']);
                }

                $member->left_at = $leftAt;
            }

            $member->membership_status = $status;
            $member->save();
            $this->access->forget();

            $this->audit->log('member.status_changed', $member, $old, ['status' => $status->value, 'left_at' => $member->left_at?->toDateString()]);

            return $member;
        });
    }

    /**
     * Attach a registered user to a member record created without an account.
     */
    public function linkUser(MessMember $member, User $user, User $actor): MessMember
    {
        if ($member->user_id) {
            throw ValidationException::withMessages(['login' => 'This member is already linked to an account.']);
        }

        if ($user->activeMembershipIn($member->mess)) {
            throw ValidationException::withMessages(['login' => 'That user already has an active membership in this mess.']);
        }

        $tenant = $member->mess->tenant;
        if (! $tenant->users()->whereKey($user->id)->exists()) {
            $this->subscriptions->assertCanAdd($tenant, 'users');
        }

        $member->user_id = $user->id;
        $member->save();
        $this->tenants->attachUser($member->mess->tenant, $user);
        $this->access->forget();
        $this->audit->log('member.user_linked', $member, new: ['user_id' => $user->id]);

        return $member;
    }

    /**
     * Create an invitation and return it with the plain token (only the hash is stored).
     *
     * @return array{0: MessInvitation, 1: string}
     */
    public function invite(Mess $mess, ?string $email, ?string $phone, int $roleId, User $actor): array
    {
        if (! Role::query()->assignableInMess($mess)->whereKey($roleId)->exists()) {
            throw ValidationException::withMessages(['role_id' => 'This role cannot be assigned in this mess.']);
        }

        $token = Str::random(48);

        $invitation = $mess->invitations()->create([
            'tenant_id' => $mess->tenant_id,
            'email' => $email,
            'phone' => $phone,
            'invitation_token_hash' => hash('sha256', $token),
            'role_id' => $roleId,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $this->audit->log('invitation.created', $invitation, new: ['email' => $email, 'phone' => $phone, 'role_id' => $roleId]);

        return [$invitation, $token];
    }

    public function findInvitation(string $token): ?MessInvitation
    {
        return MessInvitation::query()->where('invitation_token_hash', hash('sha256', $token))->first();
    }

    /**
     * Accept an invitation as $user. The invitation must be addressed to the
     * user's email or phone so a leaked link cannot be redeemed by someone else.
     */
    public function acceptInvitation(MessInvitation $invitation, User $user): MessMember
    {
        return DB::transaction(function () use ($invitation, $user) {
            $invitation = MessInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            if (! $invitation->isUsable()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation has expired or was already used.']);
            }

            $matches = ($invitation->email && $user->email && strcasecmp($invitation->email, $user->email) === 0)
                || ($invitation->phone && $user->phone && $invitation->phone === $user->phone);

            if (! $matches) {
                throw ValidationException::withMessages(['invitation' => 'This invitation was sent to a different email or phone number.']);
            }

            $mess = $invitation->mess;
            $inviter = $invitation->inviter ?? $user;
            $member = $this->addUser($mess, $user, array_filter([$invitation->role_id]), $inviter);

            $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now(), 'member_id' => $member->id])->save();
            $this->audit->log('invitation.accepted', $invitation, new: ['user_id' => $user->id, 'member_id' => $member->id], tenantId: $mess->tenant_id, messId: $mess->id);

            return $member;
        });
    }

    public function revokeInvitation(MessInvitation $invitation): void
    {
        if ($invitation->status !== 'pending') {
            throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be revoked.']);
        }

        $invitation->update(['status' => 'revoked']);
        $this->audit->log('invitation.revoked', $invitation);
    }

    /**
     * Plan limits: members per mess, and organization users when $user is new to the tenant.
     */
    private function assertPlanAllows(Mess $mess, ?User $user = null): void
    {
        $this->subscriptions->assertCanAdd($mess->tenant, 'members', $mess);

        if ($user && ! $mess->tenant->users()->whereKey($user->id)->exists() && $mess->tenant->owner_id !== $user->id) {
            $this->subscriptions->assertCanAdd($mess->tenant, 'users');
        }
    }

    private function nextMemberCode(Mess $mess): string
    {
        $count = MessMember::withTrashed()->where('mess_id', $mess->id)->count();

        do {
            $code = 'M'.str_pad((string) ++$count, 4, '0', STR_PAD_LEFT);
        } while (MessMember::withTrashed()->where('mess_id', $mess->id)->where('member_code', $code)->exists());

        return $code;
    }
}

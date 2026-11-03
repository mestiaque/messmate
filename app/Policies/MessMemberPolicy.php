<?php

namespace App\Policies;

use App\Models\MessMember;
use App\Models\User;

class MessMemberPolicy
{
    /**
     * Members can always see their own record and meal history.
     */
    public function view(User $user, MessMember $member): bool
    {
        return $member->user_id === $user->id || $user->can('viewMembers', $member->mess);
    }

    public function update(User $user, MessMember $member): bool
    {
        return $user->can('manageMembers', $member->mess);
    }
}

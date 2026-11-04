<?php

namespace App\Policies;

use App\Models\Mess;
use App\Models\User;
use App\Services\Access\MessAccessService;
use App\Support\Tenancy\Permissions;

class MessPolicy
{
    public function __construct(private readonly MessAccessService $access) {}

    public function view(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MESS_VIEW);
    }

    public function update(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MESS_UPDATE);
    }

    public function viewMembers(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MEMBERS_VIEW);
    }

    public function manageMembers(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MEMBERS_MANAGE);
    }

    public function manageRoles(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::ROLES_MANAGE);
    }

    public function managePeriods(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::PERIODS_MANAGE);
    }

    public function reopenPeriods(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::PERIODS_REOPEN);
    }

    public function manageMealTypes(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MEAL_TYPES_MANAGE);
    }

    public function viewMeals(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MEALS_VIEW);
    }

    public function manageMeals(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MEALS_MANAGE);
    }

    public function approveMeals(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::MEALS_APPROVE);
    }

    public function manageBazar(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::BAZAR_MANAGE);
    }

    public function approveBazar(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::BAZAR_APPROVE);
    }

    public function manageExpenses(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::EXPENSES_MANAGE);
    }

    public function approveExpenses(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::EXPENSES_APPROVE);
    }

    public function manageDeposits(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::DEPOSITS_MANAGE);
    }

    public function approveDeposits(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::DEPOSITS_APPROVE);
    }

    public function viewFinance(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::FINANCE_VIEW);
    }

    public function manageFinanceSettings(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::FINANCE_SETTINGS);
    }

    public function manageSettlements(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::SETTLEMENTS_MANAGE);
    }

    public function paySettlements(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::SETTLEMENTS_PAY);
    }

    public function manageAdjustments(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::ADJUSTMENTS_MANAGE);
    }

    public function manageAnnouncements(User $user, Mess $mess): bool
    {
        return $this->access->allows($user, $mess, Permissions::ANNOUNCEMENTS_MANAGE);
    }
}

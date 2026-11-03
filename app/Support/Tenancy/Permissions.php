<?php

namespace App\Support\Tenancy;

use App\Models\Role;

/**
 * Catalogue of mess-scoped permissions and the default system role grants.
 * Later phases add their modules here; the seeder syncs the database from it.
 */
final class Permissions
{
    public const MESS_VIEW = 'mess.view';
    public const MESS_UPDATE = 'mess.update';
    public const MEMBERS_VIEW = 'members.view';
    public const MEMBERS_MANAGE = 'members.manage';
    public const ROLES_MANAGE = 'roles.manage';
    public const PERIODS_MANAGE = 'periods.manage';
    public const PERIODS_REOPEN = 'periods.reopen';
    public const MEAL_TYPES_MANAGE = 'meal_types.manage';
    public const MEALS_VIEW = 'meals.view';
    public const MEALS_MANAGE = 'meals.manage';
    public const MEALS_APPROVE = 'meals.approve';
    public const BAZAR_MANAGE = 'bazar.manage';
    public const BAZAR_APPROVE = 'bazar.approve';
    public const EXPENSES_MANAGE = 'expenses.manage';
    public const EXPENSES_APPROVE = 'expenses.approve';
    public const DEPOSITS_MANAGE = 'deposits.manage';
    public const DEPOSITS_APPROVE = 'deposits.approve';
    public const FINANCE_VIEW = 'finance.view';
    public const FINANCE_SETTINGS = 'finance.settings';
    public const SETTLEMENTS_MANAGE = 'settlements.manage';
    public const SETTLEMENTS_PAY = 'settlements.pay';
    public const ADJUSTMENTS_MANAGE = 'adjustments.manage';

    /**
     * @return array<string, array{module: string, description: string}>
     */
    public static function all(): array
    {
        return [
            self::MESS_VIEW => ['module' => 'mess', 'description' => 'View the mess dashboard'],
            self::MESS_UPDATE => ['module' => 'mess', 'description' => 'Edit mess details and settings'],
            self::MEMBERS_VIEW => ['module' => 'members', 'description' => 'View the member list'],
            self::MEMBERS_MANAGE => ['module' => 'members', 'description' => 'Add and update members'],
            self::ROLES_MANAGE => ['module' => 'roles', 'description' => 'Assign mess roles to members'],
            self::PERIODS_MANAGE => ['module' => 'periods', 'description' => 'Create accounting periods and move them through their lifecycle'],
            self::PERIODS_REOPEN => ['module' => 'periods', 'description' => 'Reopen finalized or locked periods'],
            self::MEAL_TYPES_MANAGE => ['module' => 'meals', 'description' => 'Configure meal types and unit factors'],
            self::MEALS_VIEW => ['module' => 'meals', 'description' => 'View all members\' meals'],
            self::MEALS_MANAGE => ['module' => 'meals', 'description' => 'Enter and correct meals, guest meals and adjustments'],
            self::MEALS_APPROVE => ['module' => 'meals', 'description' => 'Approve, reject and void meal records'],
            self::BAZAR_MANAGE => ['module' => 'bazar', 'description' => 'Record and edit bazar purchases'],
            self::BAZAR_APPROVE => ['module' => 'bazar', 'description' => 'Approve, reject and void bazar purchases'],
            self::EXPENSES_MANAGE => ['module' => 'expenses', 'description' => 'Record and edit expenses'],
            self::EXPENSES_APPROVE => ['module' => 'expenses', 'description' => 'Approve, reject and void expenses'],
            self::DEPOSITS_MANAGE => ['module' => 'deposits', 'description' => 'Record member deposits'],
            self::DEPOSITS_APPROVE => ['module' => 'deposits', 'description' => 'Approve, reject and reverse deposits'],
            self::FINANCE_VIEW => ['module' => 'finance', 'description' => 'View ledger, balances and financial reports'],
            self::FINANCE_SETTINGS => ['module' => 'finance', 'description' => 'Manage accounts and bazar/expense categories'],
            self::SETTLEMENTS_MANAGE => ['module' => 'settlements', 'description' => 'Configure, preview and finalize monthly settlements'],
            self::SETTLEMENTS_PAY => ['module' => 'settlements', 'description' => 'Record settlement collections and refunds'],
            self::ADJUSTMENTS_MANAGE => ['module' => 'settlements', 'description' => 'Record and approve member credit/debit adjustments'],
        ];
    }

    /**
     * @return array<string, array{name: string, permissions: list<string>}>
     */
    public static function systemMessRoles(): array
    {
        $all = array_keys(self::all());

        return [
            Role::MESS_ADMIN => ['name' => 'Mess Administrator', 'permissions' => $all],
            Role::MANAGER => ['name' => 'Manager', 'permissions' => [
                self::MESS_VIEW, self::MEMBERS_VIEW, self::MEMBERS_MANAGE, self::PERIODS_MANAGE,
                self::MEAL_TYPES_MANAGE, self::MEALS_VIEW, self::MEALS_MANAGE, self::MEALS_APPROVE,
                self::BAZAR_MANAGE, self::BAZAR_APPROVE, self::EXPENSES_MANAGE, self::DEPOSITS_MANAGE, self::FINANCE_VIEW,
            ]],
            Role::ACCOUNTANT => ['name' => 'Accountant', 'permissions' => [
                self::MESS_VIEW, self::MEMBERS_VIEW, self::MEALS_VIEW, self::BAZAR_APPROVE, self::EXPENSES_MANAGE,
                self::EXPENSES_APPROVE, self::DEPOSITS_MANAGE, self::DEPOSITS_APPROVE, self::FINANCE_VIEW, self::FINANCE_SETTINGS,
                self::SETTLEMENTS_MANAGE, self::SETTLEMENTS_PAY, self::ADJUSTMENTS_MANAGE,
            ]],
            Role::BAZAR_MANAGER => ['name' => 'Bazar Manager', 'permissions' => [self::MESS_VIEW, self::MEMBERS_VIEW, self::MEALS_VIEW, self::BAZAR_MANAGE]],
            Role::MEMBER => ['name' => 'Member', 'permissions' => [self::MESS_VIEW]],
        ];
    }
}

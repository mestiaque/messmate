<?php

namespace App\Services\Finance;

use App\Enums\AccountType;
use App\Enums\ExpenseCategoryType;
use App\Enums\PaymentMethod;
use App\Models\MessMember;
use Illuminate\Validation\ValidationException;
use App\Models\FinancialAccount;
use App\Models\Mess;

/**
 * Idempotent per-mess finance defaults: system ledger accounts and starter
 * bazar / expense categories.
 */
class FinanceSetupService
{
    /** @var array<string, array{0: string, 1: AccountType}> */
    public const SYSTEM_ACCOUNTS = [
        FinancialAccount::CASH => ['Cash in hand', AccountType::Cash],
        FinancialAccount::BANK => ['Bank account', AccountType::Bank],
        FinancialAccount::MOBILE_WALLET => ['Mobile wallet', AccountType::MobileWallet],
        FinancialAccount::MEMBER_CREDIT => ['Member balances', AccountType::Payable],
        FinancialAccount::PAYABLE => ['Vendor payables', AccountType::Payable],
        FinancialAccount::FOOD_EXPENSE => ['Food expense', AccountType::Expense],
        FinancialAccount::OTHER_EXPENSE => ['Other expenses', AccountType::Expense],
        FinancialAccount::EXPENSE_RECOVERY => ['Settlement recoveries', AccountType::Income],
    ];

    public const BAZAR_CATEGORIES = [
        'Rice' => true, 'Fish' => true, 'Meat' => true, 'Vegetables' => true, 'Grocery' => true,
        'Spices & oil' => true, 'Cleaning supplies' => false, 'Gas cylinder' => false,
    ];

    public const EXPENSE_CATEGORIES = [
        'Rent' => ExpenseCategoryType::Rent,
        'Electricity' => ExpenseCategoryType::Utility,
        'Gas' => ExpenseCategoryType::Utility,
        'Water' => ExpenseCategoryType::Utility,
        'Internet' => ExpenseCategoryType::Utility,
        'Cleaning' => ExpenseCategoryType::Maintenance,
        'Maintenance' => ExpenseCategoryType::Maintenance,
        'Salary' => ExpenseCategoryType::Salary,
        'Food (manual)' => ExpenseCategoryType::Food,
        'Miscellaneous' => ExpenseCategoryType::Other,
    ];

    public function ensureDefaults(Mess $mess): void
    {
        foreach (array_keys(self::SYSTEM_ACCOUNTS) as $code) {
            $this->account($mess, $code);
        }

        if (! $mess->bazarCategories()->exists()) {
            foreach (self::BAZAR_CATEGORIES as $name => $food) {
                $mess->bazarCategories()->create(['tenant_id' => $mess->tenant_id, 'name' => $name, 'is_food_related' => $food]);
            }
        }

        if (! $mess->expenseCategories()->exists()) {
            foreach (self::EXPENSE_CATEGORIES as $name => $type) {
                $mess->expenseCategories()->create(['tenant_id' => $mess->tenant_id, 'name' => $name, 'category_type' => $type]);
            }
        }
    }

    public function account(Mess $mess, string $code): FinancialAccount
    {
        [$name, $type] = self::SYSTEM_ACCOUNTS[$code];

        return $mess->financialAccounts()->firstOrCreate(
            ['system_code' => $code],
            ['tenant_id' => $mess->tenant_id, 'name' => $name, 'account_type' => $type, 'currency' => $mess->currency],
        );
    }

    /**
     * The account (and member, for member-paid) credited when something is paid by $method.
     *
     * @return array{account: FinancialAccount, member: ?MessMember}
     */
    public function paymentSource(Mess $mess, PaymentMethod $method, ?MessMember $payer): array
    {
        if ($method === PaymentMethod::MemberPaid && ! $payer) {
            throw ValidationException::withMessages(['payment_method' => 'Choose which member paid.']);
        }

        return match ($method) {
            PaymentMethod::Cash => ['account' => $this->account($mess, FinancialAccount::CASH), 'member' => null],
            PaymentMethod::Bank => ['account' => $this->account($mess, FinancialAccount::BANK), 'member' => null],
            PaymentMethod::MobileWallet => ['account' => $this->account($mess, FinancialAccount::MOBILE_WALLET), 'member' => null],
            PaymentMethod::MemberPaid => ['account' => $this->account($mess, FinancialAccount::MEMBER_CREDIT), 'member' => $payer],
            PaymentMethod::Credit => ['account' => $this->account($mess, FinancialAccount::PAYABLE), 'member' => null],
        };
    }
}

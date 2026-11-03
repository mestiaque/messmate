<?php

namespace App\Services\Finance;

use App\Enums\AllocationMethod;
use App\Enums\EntryStatus;
use App\Enums\ExpenseCategoryType;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\LedgerTransaction;
use App\Models\Mess;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Periods\AccountingPeriodService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manually recorded expenses. Bazar purchases are never copied here, so each
 * purchase has exactly one authoritative record (see BUSINESS_RULES.md).
 */
class ExpenseService
{
    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly FinanceSetupService $setup,
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{category_id: int, title: string, description?: ?string, amount: string, expense_date: string, allocation_method: string, payment_method: string, paid_by_member_id?: ?int, shares?: array<int, string>}  $data
     */
    public function save(Mess $mess, array $data, User $actor, ?Expense $expense = null, ?string $receiptPath = null): Expense
    {
        if ($expense && ! $expense->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Approved or voided expenses cannot be edited. Void it and record a corrected one.']);
        }

        $period = $this->periods->openPeriodFor($mess, $data['expense_date'], 'expense_date');
        $category = $mess->expenseCategories()->where('is_active', true)->find($data['category_id'])
            ?? throw ValidationException::withMessages(['category_id' => 'Choose an active category.']);
        $method = AllocationMethod::from($data['allocation_method']);
        $amount = Decimal::of($data['amount'], 2);

        if (Decimal::compare($amount, '0') <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        // Food expenses feed the meal-rate pool, so members pay them through their meal units.
        if ($category->category_type === ExpenseCategoryType::Food && $method !== AllocationMethod::MealBased) {
            throw ValidationException::withMessages(['allocation_method' => 'Food expenses are shared by meals; use meal-based allocation.']);
        }

        $payer = null;
        if (! empty($data['paid_by_member_id'])) {
            $payer = $mess->members()->find($data['paid_by_member_id'])
                ?? throw ValidationException::withMessages(['paid_by_member_id' => 'Payer must be a member of this mess.']);
        }

        if ($data['payment_method'] === PaymentMethod::MemberPaid->value && ! $payer) {
            throw ValidationException::withMessages(['paid_by_member_id' => 'Choose which member paid.']);
        }

        $allocations = $method->needsAllocations() ? $this->allocations($mess, $method, $amount, $data['shares'] ?? []) : [];

        return DB::transaction(function () use ($mess, $data, $actor, $expense, $receiptPath, $period, $category, $method, $amount, $payer, $allocations) {
            $isNew = ! $expense;
            $old = $expense?->only(['amount', 'allocation_method', 'status']);
            $expense ??= new Expense(['tenant_id' => $mess->tenant_id, 'mess_id' => $mess->id, 'created_by' => $actor->id, 'expense_source' => 'manual']);

            $expense->fill([
                'period_id' => $period->id,
                'category_id' => $category->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'expense_date' => $data['expense_date'],
                'allocation_method' => $method,
                'payment_method' => $data['payment_method'],
                'paid_by_member_id' => $payer?->id,
                'status' => EntryStatus::Submitted,
                'rejection_reason' => null,
            ]);

            if ($receiptPath) {
                $expense->receipt_path = $receiptPath;
            }

            $expense->save();
            $expense->allocations()->delete();
            $expense->allocations()->createMany(array_map(fn ($a) => $a + ['mess_id' => $mess->id], $allocations));

            $this->audit->log($isNew ? 'expense.created' : 'expense.updated', $expense, $old ?? [], $expense->only(['amount', 'allocation_method', 'status']));

            return $expense;
        });
    }

    /**
     * Custom: amounts must add up exactly. Usage-based: amounts are proportional
     * to usage, with leftover paisa assigned by largest remainder.
     *
     * @param  array<int|string, string|null>  $shares  member_id => amount (custom) or usage (usage_based)
     * @return list<array{member_id: int, allocated_amount: string, allocation_basis: ?string}>
     */
    public function allocations(Mess $mess, AllocationMethod $method, string $amount, array $shares): array
    {
        $shares = array_filter($shares, fn ($v) => $v !== null && $v !== '' && ! Decimal::isZero(Decimal::of($v)));

        if (! $shares) {
            throw ValidationException::withMessages(['shares' => 'Enter at least one member share.']);
        }

        $memberIds = array_map('intval', array_keys($shares));
        if ($mess->members()->whereIn('id', $memberIds)->count() !== count($memberIds)) {
            throw ValidationException::withMessages(['shares' => 'Shares can only be assigned to members of this mess.']);
        }

        foreach ($shares as $value) {
            if (Decimal::compare(Decimal::of($value), '0') < 0) {
                throw ValidationException::withMessages(['shares' => 'Shares cannot be negative.']);
            }
        }

        if ($method === AllocationMethod::Custom) {
            $sum = Decimal::sum($shares, 2);

            if (Decimal::compare($sum, $amount, 2) !== 0) {
                throw ValidationException::withMessages(['shares' => "Custom shares add up to {$sum}, but the expense is {$amount}."]);
            }

            return array_map(fn ($id) => ['member_id' => (int) $id, 'allocated_amount' => Decimal::of($shares[$id], 2), 'allocation_basis' => null], array_keys($shares));
        }

        $amounts = Decimal::allocate($amount, $shares);

        return array_map(fn ($id) => [
            'member_id' => (int) $id,
            'allocated_amount' => $amounts[$id],
            'allocation_basis' => Decimal::of($shares[$id], 4),
        ], array_keys($shares));
    }

    public function approve(Expense $expense, User $actor): Expense
    {
        return DB::transaction(function () use ($expense, $actor) {
            $expense = Expense::query()->lockForUpdate()->with('category', 'paidBy', 'period')->findOrFail($expense->id);
            $this->assertStatus($expense, EntryStatus::Submitted);

            $mess = Mess::findOrFail($expense->mess_id);
            $source = $this->setup->paymentSource($mess, $expense->payment_method, $expense->paidBy);
            $debit = $expense->category->category_type === ExpenseCategoryType::Food ? FinancialAccount::FOOD_EXPENSE : FinancialAccount::OTHER_EXPENSE;

            $tx = $this->ledger->post($mess, $expense->expense_date->toDateString(), "Expense: {$expense->title}", [
                ['account' => $this->setup->account($mess, $debit), 'debit' => $expense->amount],
                ['account' => $source['account'], 'member' => $source['member'], 'credit' => $expense->amount],
            ], $actor, $expense);

            $expense->forceFill(['status' => EntryStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now(), 'ledger_transaction_id' => $tx->id])->save();
            $this->audit->log('expense.approved', $expense, new: ['ledger' => $tx->transaction_number]);

            return $expense;
        });
    }

    public function reject(Expense $expense, User $actor, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $reason) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->assertStatus($expense, EntryStatus::Submitted);
            $expense->forceFill(['status' => EntryStatus::Rejected, 'rejection_reason' => $reason])->save();
            $this->audit->log('expense.rejected', $expense, new: ['reason' => $reason]);

            return $expense;
        });
    }

    public function void(Expense $expense, User $actor, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $actor, $reason) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($expense->status === EntryStatus::Voided) {
                throw ValidationException::withMessages(['status' => 'Already voided.']);
            }

            if (! $expense->period->status->acceptsEntries()) {
                throw ValidationException::withMessages(['status' => "The period \"{$expense->period->name}\" is not open."]);
            }

            if ($expense->ledger_transaction_id) {
                $this->ledger->reverse(LedgerTransaction::findOrFail($expense->ledger_transaction_id), $actor, $reason);
            }

            $expense->forceFill(['status' => EntryStatus::Voided, 'rejection_reason' => $reason])->save();
            $this->audit->log('expense.voided', $expense, new: ['reason' => $reason]);

            return $expense;
        });
    }

    private function assertStatus(Expense $expense, EntryStatus $required): void
    {
        if ($expense->status !== $required) {
            throw ValidationException::withMessages(['status' => "This expense is {$expense->status->value}."]);
        }

        if (! $expense->period->status->acceptsApprovals()) {
            throw ValidationException::withMessages(['status' => "The period \"{$expense->period->name}\" is {$expense->period->status->value}."]);
        }
    }
}

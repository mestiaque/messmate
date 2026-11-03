<?php

namespace App\Services\Finance;

use App\Enums\EntryStatus;
use App\Enums\PaymentMethod;
use App\Models\BazarEntry;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Periods\AccountingPeriodService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bazar purchases. Totals are always computed here from the submitted inputs;
 * nothing the browser calls a "total" is trusted.
 */
class BazarService
{
    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly FinanceSetupService $setup,
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{purchase_date: string, purchased_by: int, entry_mode: string, payment_method: string, description?: ?string, category_id?: ?int, amount?: ?string, discount?: ?string, items?: list<array>}  $data
     */
    public function save(Mess $mess, array $data, User $actor, ?BazarEntry $entry = null, ?string $receiptPath = null): BazarEntry
    {
        if ($entry && ! $entry->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Approved or voided purchases cannot be edited. Void it and record a corrected one.']);
        }

        $period = $this->periods->openPeriodFor($mess, $data['purchase_date'], 'purchase_date');
        $purchaser = $mess->members()->find($data['purchased_by'])
            ?? throw ValidationException::withMessages(['purchased_by' => 'Purchaser must be a member of this mess.']);
        $amounts = $this->computeAmounts($mess, $data);

        return DB::transaction(function () use ($mess, $data, $actor, $entry, $receiptPath, $period, $purchaser, $amounts) {
            $isNew = ! $entry;
            $old = $entry?->only(['total_amount', 'food_amount', 'purchase_date', 'status']);
            $entry ??= new BazarEntry(['tenant_id' => $mess->tenant_id, 'mess_id' => $mess->id, 'created_by' => $actor->id]);

            $entry->fill([
                'period_id' => $period->id,
                'purchased_by' => $purchaser->id,
                'purchase_date' => $data['purchase_date'],
                'entry_mode' => $data['entry_mode'],
                'category_id' => $data['entry_mode'] === 'total' ? $data['category_id'] : null,
                'description' => $data['description'] ?? null,
                'subtotal' => $amounts['subtotal'],
                'discount' => $amounts['discount'],
                'total_amount' => $amounts['total'],
                'food_amount' => $amounts['food'],
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_method'] === PaymentMethod::Credit->value ? 'unpaid' : 'paid',
                'status' => EntryStatus::Submitted,
                'rejection_reason' => null,
            ]);

            if ($receiptPath) {
                $entry->receipt_path = $receiptPath;
            }

            $entry->save();
            $entry->items()->delete();
            $entry->items()->createMany($amounts['items']);

            $this->audit->log($isNew ? 'bazar.created' : 'bazar.updated', $entry, $old ?? [], $entry->only(['total_amount', 'food_amount', 'purchase_date', 'status']));

            return $entry;
        });
    }

    /**
     * @return array{subtotal: string, discount: string, total: string, food: string, items: list<array>}
     */
    public function computeAmounts(Mess $mess, array $data): array
    {
        $categories = $mess->bazarCategories()->where('is_active', true)->get()->keyBy('id');
        $discount = Decimal::of($data['discount'] ?? '0', 2);
        $items = [];

        if ($data['entry_mode'] === 'total') {
            $category = $categories->get((int) ($data['category_id'] ?? 0))
                ?? throw ValidationException::withMessages(['category_id' => 'Choose an active category.']);
            $subtotal = Decimal::of($data['amount'] ?? '0', 2);
            $foodSubtotal = $category->is_food_related ? $subtotal : '0.00';
        } else {
            $subtotal = '0.00';
            $foodSubtotal = '0.00';

            foreach ($data['items'] ?? [] as $i => $item) {
                $category = $categories->get((int) ($item['category_id'] ?? 0))
                    ?? throw ValidationException::withMessages(["items.{$i}.category_id" => 'Choose an active category.']);
                $quantity = Decimal::of($item['quantity'], 4);
                $unitPrice = Decimal::of($item['unit_price'], 4);
                $lineDiscount = Decimal::of($item['discount'] ?? '0', 2);
                $lineTotal = bcsub(Decimal::mul($quantity, $unitPrice, 2), $lineDiscount, 2);

                if (Decimal::compare($lineTotal, '0') < 0) {
                    throw ValidationException::withMessages(["items.{$i}.discount" => 'Item discount is larger than the item price.']);
                }

                $items[] = [
                    'item_name' => $item['item_name'],
                    'category_id' => $category->id,
                    'quantity' => $quantity,
                    'unit' => $item['unit'],
                    'unit_price' => $unitPrice,
                    'discount' => $lineDiscount,
                    'total' => $lineTotal,
                ];
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $foodSubtotal = $category->is_food_related ? bcadd($foodSubtotal, $lineTotal, 2) : $foodSubtotal;
            }

            if (! $items) {
                throw ValidationException::withMessages(['items' => 'Add at least one item.']);
            }
        }

        if (Decimal::compare($discount, $subtotal) > 0) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the subtotal.']);
        }

        $total = bcsub($subtotal, $discount, 2);

        if (Decimal::compare($total, '0') <= 0) {
            throw ValidationException::withMessages(['amount' => 'The purchase total must be greater than zero.']);
        }

        // Spread the entry-level discount pro rata over the food share.
        $food = Decimal::compare($subtotal, '0') > 0
            ? Decimal::round(bcdiv(bcmul($foodSubtotal, $total, 8), $subtotal, 8), 2)
            : '0.00';

        return ['subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'food' => $food, 'items' => $items];
    }

    public function approve(BazarEntry $entry, User $actor): BazarEntry
    {
        return DB::transaction(function () use ($entry, $actor) {
            $entry = BazarEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->assertApprovable($entry, EntryStatus::Submitted);

            $mess = Mess::findOrFail($entry->mess_id);
            $source = $this->setup->paymentSource($mess, $entry->payment_method, $entry->purchaser);
            $other = bcsub($entry->total_amount, $entry->food_amount, 2);

            $tx = $this->ledger->post($mess, $entry->purchase_date->toDateString(), "Bazar #{$entry->id}".($entry->description ? ": {$entry->description}" : ''), [
                ['account' => $this->setup->account($mess, FinancialAccount::FOOD_EXPENSE), 'debit' => $entry->food_amount],
                ['account' => $this->setup->account($mess, FinancialAccount::OTHER_EXPENSE), 'debit' => $other],
                ['account' => $source['account'], 'member' => $source['member'], 'credit' => $entry->total_amount],
            ], $actor, $entry);

            $entry->forceFill(['status' => EntryStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now(), 'ledger_transaction_id' => $tx->id])->save();
            $this->audit->log('bazar.approved', $entry, new: ['ledger' => $tx->transaction_number]);

            return $entry;
        });
    }

    public function reject(BazarEntry $entry, User $actor, string $reason): BazarEntry
    {
        return DB::transaction(function () use ($entry, $reason) {
            $entry = BazarEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->assertApprovable($entry, EntryStatus::Submitted);
            $entry->forceFill(['status' => EntryStatus::Rejected, 'rejection_reason' => $reason])->save();
            $this->audit->log('bazar.rejected', $entry, new: ['reason' => $reason]);

            return $entry;
        });
    }

    /**
     * Void a purchase; an approved one has its ledger posting reversed.
     */
    public function void(BazarEntry $entry, User $actor, string $reason): BazarEntry
    {
        return DB::transaction(function () use ($entry, $actor, $reason) {
            $entry = BazarEntry::query()->lockForUpdate()->with('ledgerTransaction')->findOrFail($entry->id);

            if ($entry->status === EntryStatus::Voided) {
                throw ValidationException::withMessages(['status' => 'Already voided.']);
            }

            if (! $entry->period->status->acceptsEntries()) {
                throw ValidationException::withMessages(['status' => "The period \"{$entry->period->name}\" is not open."]);
            }

            if ($entry->ledgerTransaction) {
                $this->ledger->reverse($entry->ledgerTransaction, $actor, $reason);
            }

            $entry->forceFill(['status' => EntryStatus::Voided, 'rejection_reason' => $reason])->save();
            $this->audit->log('bazar.voided', $entry, new: ['reason' => $reason]);

            return $entry;
        });
    }

    private function assertApprovable(BazarEntry $entry, EntryStatus $required): void
    {
        if ($entry->status !== $required) {
            throw ValidationException::withMessages(['status' => "This purchase is {$entry->status->value}."]);
        }

        if (! $entry->period->status->acceptsApprovals()) {
            throw ValidationException::withMessages(['status' => "The period \"{$entry->period->name}\" is {$entry->period->status->value}."]);
        }
    }
}

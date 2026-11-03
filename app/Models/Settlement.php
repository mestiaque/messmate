<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    public const DRAFT = 'draft';
    public const FINALIZED = 'finalized';
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';
    public const SUPERSEDED = 'superseded';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_credit' => 'decimal:2',
            'meal_units' => 'decimal:4',
            'meal_cost' => 'decimal:2',
            'guest_cost' => 'decimal:2',
            'other_expense' => 'decimal:2',
            'other_credits' => 'decimal:2',
            'other_debits' => 'decimal:2',
            'total_deposits' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'closing_credit' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(MonthlySnapshot::class, 'snapshot_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'member_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SettlementPayment::class);
    }

    /**
     * Amount still to collect (member owes) or refund (mess owes), always >= 0.
     */
    public function outstanding(): string
    {
        return bcsub(ltrim((string) $this->closing_credit, '-'), (string) $this->paid_amount, 2);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [self::FINALIZED, self::PARTIALLY_PAID], true) && bccomp($this->outstanding(), '0', 2) > 0;
    }
}

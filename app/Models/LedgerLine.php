<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LedgerLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger lines cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Ledger lines cannot be deleted.'));
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'member_id');
    }
}

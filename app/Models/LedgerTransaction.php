<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Posted transactions are immutable: the only permitted change is marking one
 * as reversed, and nothing may be deleted. Corrections are new transactions.
 */
class LedgerTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $tx) {
            $dirty = array_keys($tx->getDirty());
            $markingReversed = $dirty === ['status'] || $dirty === ['status', 'updated_at'] || $dirty === ['updated_at', 'status'];

            if ($tx->getOriginal('status') === 'posted' && ! ($markingReversed && $tx->status === 'reversed')) {
                throw new LogicException('Posted ledger transactions cannot be edited; post a reversal instead.');
            }
        });

        static::deleting(fn () => throw new LogicException('Ledger transactions cannot be deleted.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'transaction_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

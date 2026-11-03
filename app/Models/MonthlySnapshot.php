<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A saved settlement calculation. Final snapshots are immutable; a reopen marks
 * them superseded and a new version is calculated.
 */
class MonthlySnapshot extends Model
{
    public const DRAFT = 'draft';
    public const FINAL = 'final';
    public const SUPERSEDED = 'superseded';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'calculation_details' => 'array',
            'generated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $snapshot) {
            $changed = array_diff(array_keys($snapshot->getDirty()), ['status', 'superseded_at', 'superseded_reason', 'updated_at']);

            if ($snapshot->getOriginal('status') !== self::DRAFT && ($changed || $snapshot->status !== self::SUPERSEDED)) {
                throw new LogicException('A final settlement snapshot cannot be changed; reopen the period to recalculate.');
            }
        });

        static::deleting(function (self $snapshot) {
            if ($snapshot->status !== self::DRAFT) {
                throw new LogicException('Only draft snapshots can be deleted.');
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'snapshot_id');
    }
}

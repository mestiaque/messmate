<?php

namespace App\Models;

use App\Enums\EntryStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BazarEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'food_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => EntryStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'purchased_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BazarCategory::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BazarItem::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [EntryStatus::Draft, EntryStatus::Submitted, EntryStatus::Rejected], true);
    }
}

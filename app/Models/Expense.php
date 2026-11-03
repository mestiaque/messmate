<?php

namespace App\Models;

use App\Enums\AllocationMethod;
use App\Enums\EntryStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'allocation_method' => AllocationMethod::class,
            'payment_method' => PaymentMethod::class,
            'status' => EntryStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'paid_by_member_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseAllocation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
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

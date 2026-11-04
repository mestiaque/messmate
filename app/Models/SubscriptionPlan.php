<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function price(string $cycle): string
    {
        return $cycle === 'yearly' ? (string) $this->yearly_price : (string) $this->monthly_price;
    }

    public function isFree(): bool
    {
        return bccomp((string) $this->monthly_price, '0', 2) === 0 && bccomp((string) $this->yearly_price, '0', 2) === 0;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}

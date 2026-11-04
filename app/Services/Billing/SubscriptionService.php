<?php

namespace App\Services\Billing;

use App\Enums\MembershipStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Mess;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant subscription state, plan limits and access policy. Access is computed
 * from dates on every check, so enforcement never depends on the sweep job
 * having run; the sweep only records status changes and history.
 */
class SubscriptionService
{
    public const FULL = 'full';
    public const READ_ONLY = 'read_only';

    /** @var array<int, Subscription> */
    private array $memo = [];

    public function start(Tenant $tenant, ?User $actor = null): Subscription
    {
        $plan = $this->defaultTrialPlan();
        $trial = $plan->trial_days > 0;

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => $trial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
            'starts_at' => now(),
            'trial_ends_at' => $trial ? now()->addDays($plan->trial_days) : null,
        ]);
        $tenant->forceFill(['trial_ends_at' => $subscription->trial_ends_at])->save();
        $this->event($subscription, $trial ? 'trial_started' : 'started', ['plan' => $plan->slug], $actor);

        return $subscription;
    }

    /**
     * The tenant's subscription; tenants created before billing existed get the default trial.
     */
    public function current(Tenant $tenant): Subscription
    {
        return $this->memo[$tenant->id] ??= Subscription::query()->with('plan')->where('tenant_id', $tenant->id)->first()
            ?? tap($this->start($tenant), fn ($s) => $s->load('plan'));
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    public function access(Tenant $tenant): string
    {
        $s = $this->current($tenant);
        $now = now();

        $full = match ($s->status) {
            SubscriptionStatus::Trial => $s->trial_ends_at === null || $s->trial_ends_at->isAfter($now),
            SubscriptionStatus::Active => $s->current_period_end === null || $s->current_period_end->copy()->addDays($this->graceDays())->isAfter($now),
            SubscriptionStatus::PastDue => $s->grace_ends_at !== null && $s->grace_ends_at->isAfter($now),
            SubscriptionStatus::Cancelled => $s->current_period_end !== null && $s->current_period_end->isAfter($now),
            SubscriptionStatus::Expired => false,
        };

        return $full ? self::FULL : self::READ_ONLY;
    }

    public function isWritable(Tenant $tenant): bool
    {
        return $this->access($tenant) === self::FULL;
    }

    /**
     * A short banner message for the tenant, or null when nothing needs attention.
     */
    public function notice(Tenant $tenant): ?array
    {
        $s = $this->current($tenant);

        if (! $this->isWritable($tenant)) {
            return ['danger', 'Your subscription has ended. Your data is safe but read-only; renew to make changes.'];
        }

        return match (true) {
            $s->status === SubscriptionStatus::Trial && $s->trial_ends_at !== null => ['info', "Free trial of {$s->plan->name}: ".max(0, (int) ceil(now()->diffInDays($s->trial_ends_at, false))).' day(s) left.'],
            $s->status === SubscriptionStatus::PastDue, $s->status === SubscriptionStatus::Active && $s->current_period_end?->isPast() => ['warning', 'Payment is overdue. Renew before '.($s->grace_ends_at ?? $s->current_period_end->copy()->addDays($this->graceDays()))->format('d M Y').' to avoid read-only mode.'],
            $s->status === SubscriptionStatus::Cancelled => ['secondary', 'Subscription cancelled; access continues until '.$s->current_period_end?->format('d M Y').'.'],
            default => null,
        };
    }

    public function plan(Tenant $tenant): SubscriptionPlan
    {
        return $this->current($tenant)->plan;
    }

    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        return $this->plan($tenant)->hasFeature($feature);
    }

    /**
     * @return array{messes: int, users: int, members: array<int, int>}
     */
    public function usage(Tenant $tenant): array
    {
        return [
            'messes' => $tenant->messes()->count(),
            'users' => $tenant->users()->wherePivot('status', 'active')->count(),
            'members' => $tenant->messes()->withCount(['members as live_count' => fn ($q) => $q->where('membership_status', '!=', MembershipStatus::Left)])->pluck('live_count', 'id')->all(),
        ];
    }

    /**
     * Throw if adding one more $what would exceed the plan. Limits are enforced
     * on the server; NULL means unlimited.
     */
    public function assertCanAdd(Tenant $tenant, string $what, ?Mess $mess = null): void
    {
        $plan = $this->plan($tenant);

        [$limit, $count, $label] = match ($what) {
            'messes' => [$plan->max_messes, $tenant->messes()->count(), 'messes'],
            'users' => [$plan->max_users, $tenant->users()->wherePivot('status', 'active')->count(), 'users'],
            'members' => [$plan->max_members_per_mess, $mess->members()->where('membership_status', '!=', MembershipStatus::Left)->count(), 'members in a mess'],
        };

        if ($limit !== null && $count >= $limit) {
            throw ValidationException::withMessages(['plan' => "The {$plan->name} plan allows up to {$limit} {$label}. Upgrade the plan to add more."]);
        }
    }

    /**
     * Downgrades must fit current usage; nothing is ever deleted to make room.
     */
    public function assertFits(Tenant $tenant, SubscriptionPlan $plan): void
    {
        $usage = $this->usage($tenant);
        $problems = [];

        if ($plan->max_messes !== null && $usage['messes'] > $plan->max_messes) {
            $problems[] = "{$usage['messes']} messes (plan allows {$plan->max_messes})";
        }
        if ($plan->max_users !== null && $usage['users'] > $plan->max_users) {
            $problems[] = "{$usage['users']} users (plan allows {$plan->max_users})";
        }
        if ($plan->max_members_per_mess !== null && $usage['members'] && max($usage['members']) > $plan->max_members_per_mess) {
            $problems[] = max($usage['members'])." members in one mess (plan allows {$plan->max_members_per_mess})";
        }

        if ($problems) {
            throw ValidationException::withMessages(['plan' => "You currently have ".implode(', ', $problems).'. Reduce usage before moving to '.$plan->name.'.']);
        }
    }

    /**
     * Apply a paid invoice: upgrade, downgrade or renewal. The plan changes at once;
     * any remaining paid time is kept and the new period is added after it.
     */
    public function applyInvoice(Invoice $invoice, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $s = Subscription::query()->lockForUpdate()->with('plan')->findOrFail($invoice->subscription_id);
            $plan = SubscriptionPlan::findOrFail($invoice->plan_id);
            $previous = $s->plan;

            // Paid time that is still running is never lost: the new period is added
            // after it, whether the plan or cycle stays the same or changes (no proration).
            $carryOver = $s->status !== SubscriptionStatus::Trial && $s->current_period_end?->isFuture();
            $start = $carryOver ? $s->current_period_end->copy() : now();
            $end = $invoice->billing_cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
            $renewal = $previous->id === $plan->id && $carryOver;

            $s->forceFill([
                'plan_id' => $plan->id,
                'billing_cycle' => $invoice->billing_cycle,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $carryOver ? $s->current_period_start : $start,
                'current_period_end' => $end,
                'grace_ends_at' => null,
                'cancelled_at' => null,
            ])->save();

            $event = match (true) {
                $renewal => 'renewed',
                $previous->id === $plan->id => 'activated',
                bccomp($plan->monthly_price, $previous->monthly_price, 2) > 0 => 'upgraded',
                default => 'downgraded',
            };
            $this->event($s, $event, ['from' => $previous->slug, 'to' => $plan->slug, 'invoice' => $invoice->invoice_number, 'until' => $end->toIso8601String()], $actor);
            $this->forget();

            return $s->fresh('plan');
        });
    }

    public function switchToFree(Tenant $tenant, SubscriptionPlan $free, User $actor): Subscription
    {
        $this->assertFits($tenant, $free);
        $s = $this->current($tenant);
        $from = $s->plan->slug;
        $s->forceFill(['plan_id' => $free->id, 'billing_cycle' => 'monthly', 'status' => SubscriptionStatus::Active, 'current_period_start' => now(), 'current_period_end' => null, 'grace_ends_at' => null, 'cancelled_at' => null, 'trial_ends_at' => null])->save();
        $this->event($s, 'downgraded', ['from' => $from, 'to' => $free->slug], $actor);
        $this->forget();

        return $s;
    }

    public function cancel(Tenant $tenant, User $actor): Subscription
    {
        $s = $this->current($tenant);

        if (! in_array($s->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true) || $s->current_period_end === null) {
            throw ValidationException::withMessages(['plan' => 'Only a paid subscription can be cancelled.']);
        }

        $s->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()])->save();
        $this->event($s, 'cancelled', ['access_until' => $s->current_period_end->toIso8601String()], $actor);
        $this->forget();

        return $s;
    }

    /**
     * Platform admin override (e.g. a sponsored or manually invoiced tenant).
     */
    public function adminSet(Tenant $tenant, SubscriptionPlan $plan, ?Carbon $until, User $admin): Subscription
    {
        $s = $this->current($tenant);
        $from = $s->plan->slug;
        $s->forceFill(['plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'current_period_start' => now(), 'current_period_end' => $until, 'grace_ends_at' => null, 'cancelled_at' => null])->save();
        $this->event($s, 'admin_changed', ['from' => $from, 'to' => $plan->slug, 'until' => $until?->toIso8601String()], $admin);
        $this->forget();

        return $s;
    }

    /**
     * Daily status transitions. Returns how many subscriptions changed.
     */
    public function sweep(): int
    {
        $changed = 0;
        $now = now();

        foreach (Subscription::query()->whereIn('status', [SubscriptionStatus::Trial, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled])->cursor() as $s) {
            [$status, $event, $extra] = match (true) {
                $s->status === SubscriptionStatus::Trial && $s->trial_ends_at?->lte($now) => [SubscriptionStatus::Expired, 'trial_expired', []],
                $s->status === SubscriptionStatus::Active && $s->current_period_end?->lte($now) => [SubscriptionStatus::PastDue, 'past_due', ['grace_ends_at' => $s->current_period_end->copy()->addDays($this->graceDays())]],
                $s->status === SubscriptionStatus::PastDue && $s->grace_ends_at?->lte($now) => [SubscriptionStatus::Expired, 'expired', []],
                $s->status === SubscriptionStatus::Cancelled && $s->current_period_end?->lte($now) => [SubscriptionStatus::Expired, 'expired', []],
                default => [null, null, []],
            };

            if ($status) {
                $s->forceFill(['status' => $status] + $extra)->save();
                $this->event($s, $event, array_map(fn ($v) => $v instanceof Carbon ? $v->toIso8601String() : $v, $extra));
                $changed++;
            }
        }

        $this->forget();

        return $changed;
    }

    public function graceDays(): int
    {
        return (int) PlatformSetting::get('grace_days', config('billing.default_grace_days'));
    }

    public function event(Subscription $s, string $event, array $data = [], ?User $actor = null): void
    {
        $s->events()->create(['tenant_id' => $s->tenant_id, 'event' => $event, 'data' => $data ?: null, 'user_id' => $actor?->id ?? auth()->id()]);
    }

    private function defaultTrialPlan(): SubscriptionPlan
    {
        $slug = PlatformSetting::get('default_trial_plan', config('billing.default_trial_plan'));

        return SubscriptionPlan::query()->where('slug', $slug)->where('is_active', true)->first()
            ?? SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();
    }
}

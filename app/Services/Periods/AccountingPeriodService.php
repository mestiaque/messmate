<?php

namespace App\Services\Periods;

use App\Enums\EntryStatus;
use App\Enums\PeriodMode;
use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\Mess;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create a calendar-month period (from $month = "YYYY-MM") or a custom range.
     */
    public function create(Mess $mess, User $actor, PeriodMode $type, ?string $month, ?string $start, ?string $end, ?string $name = null, ?string $notes = null): AccountingPeriod
    {
        if ($type === PeriodMode::Calendar) {
            $first = Carbon::createFromFormat('!Y-m', (string) $month);
            $startDate = $first->copy()->startOfMonth();
            $endDate = $first->copy()->endOfMonth()->startOfDay();
            $name = $name ?: $startDate->format('F Y');
        } else {
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = Carbon::parse($end)->startOfDay();
            $name = $name ?: $startDate->format('d M').' – '.$endDate->format('d M Y');
        }

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages(['end_date' => 'The end date cannot be before the start date.']);
        }

        return DB::transaction(function () use ($mess, $actor, $type, $startDate, $endDate, $name, $notes) {
            // Serialise period creation per mess so two concurrent requests cannot both pass the overlap check.
            Mess::query()->whereKey($mess->id)->lockForUpdate()->first();

            $overlap = $mess->periods()
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate)
                ->first();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_date' => "These dates overlap the existing period \"{$overlap->name}\" ({$overlap->start_date->toDateString()} – {$overlap->end_date->toDateString()}).",
                ]);
            }

            $period = $mess->periods()->create([
                'tenant_id' => $mess->tenant_id,
                'name' => $name,
                'period_type' => $type,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => PeriodStatus::Draft,
                'notes' => $notes,
            ]);

            $this->audit->log('period.created', $period, new: $period->only(['name', 'start_date', 'end_date']));

            return $period;
        });
    }

    public function transition(AccountingPeriod $period, PeriodStatus $to, User $actor): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $to, $actor) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $from = $period->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw ValidationException::withMessages(['status' => "A {$from->value} period cannot move to {$to->value}."]);
            }

            // Finalizing always goes through SettlementService so a period is never final without its settlement.
            if ($to === PeriodStatus::Finalized) {
                throw ValidationException::withMessages(['status' => 'Finalize the period from the settlement page.']);
            }

            $period->status = $to;

            match ($to) {
                PeriodStatus::Open => $period->opened_by ??= $actor->id,
                PeriodStatus::Finalized => $period->forceFill(['finalized_by' => $actor->id, 'finalized_at' => now()]),
                PeriodStatus::Locked => $period->locked_at = now(),
                default => null,
            };

            $period->save();
            $this->audit->log('period.status_changed', $period, ['status' => $from->value], ['status' => $to->value]);

            return $period;
        });
    }

    /**
     * Controlled reopening of a finalized or locked period. Requires a reason and
     * leaves an audit record; the caller must hold periods.reopen.
     */
    public function reopen(AccountingPeriod $period, User $actor, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $reason) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $from = $period->status;

            if (! in_array($from, [PeriodStatus::Finalized, PeriodStatus::Locked], true)) {
                throw ValidationException::withMessages(['status' => 'Only finalized or locked periods can be reopened.']);
            }

            $old = $period->only(['status', 'finalized_by', 'finalized_at', 'locked_at']);
            $period->forceFill(['status' => PeriodStatus::Open, 'finalized_by' => null, 'finalized_at' => null, 'locked_at' => null])->save();

            $this->audit->log('period.reopened', $period, $old, ['status' => PeriodStatus::Open->value, 'reason' => $reason]);

            return $period;
        });
    }

    public function periodFor(Mess $mess, \DateTimeInterface|string $date): ?AccountingPeriod
    {
        $date = Carbon::parse($date)->toDateString();

        return $mess->periods()->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first();
    }

    /**
     * The period covering $date, which must be open for entries.
     */
    public function openPeriodFor(Mess $mess, \DateTimeInterface|string $date, string $field = 'meal_date'): AccountingPeriod
    {
        $period = $this->periodFor($mess, $date);

        if (! $period) {
            throw ValidationException::withMessages([$field => 'No accounting period covers '.Carbon::parse($date)->toDateString().'.']);
        }

        if (! $period->status->acceptsEntries()) {
            throw ValidationException::withMessages([$field => "The period \"{$period->name}\" is {$period->status->value}; entries cannot be changed."]);
        }

        return $period;
    }

    public function pendingCount(AccountingPeriod $period): int
    {
        return $period->mealEntries()->where('status', EntryStatus::Submitted)->count()
            + $period->guestMeals()->where('status', EntryStatus::Submitted)->count()
            + $period->mealAdjustments()->where('status', EntryStatus::Submitted)->count();
    }
}

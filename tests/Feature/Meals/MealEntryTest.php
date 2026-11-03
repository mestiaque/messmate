<?php

namespace Tests\Feature\Meals;

use App\Enums\EntryStatus;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\MealEntry;
use App\Models\Role;
use App\Services\Meals\GuestMealService;
use App\Services\Meals\MealApprovalService;
use App\Services\Meals\MealEntryService;
use App\Services\Meals\MealTypeService;
use App\Services\Tenancy\MembershipService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MealEntryTest extends TestCase
{
    use RefreshDatabase;

    private function meals(): MealEntryService
    {
        return app(MealEntryService::class);
    }

    public function test_individual_tracking_applies_meal_factors(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $t = $this->mealTypeIds($mess);

        $entry = $this->meals()->saveIndividual($mess, $member, $period->start_date->toDateString(),
            [$t['breakfast'] => 1, $t['lunch'] => 1, $t['dinner'] => 1], $mess->tenant->owner);

        $this->assertSame('2.5000', $entry->total_units);
        $this->assertSame(EntryStatus::Submitted, $entry->status);
        $this->assertSame(['0.5000', '1.0000', '1.0000'], $entry->items()->orderBy('meal_type_id')->pluck('weighted_units')->all());
    }

    public function test_daily_total_tracking_stores_units_and_derivation(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $a = $this->memberOf($mess, Role::MEMBER);
        $b = $this->memberOf($mess, Role::MEMBER);
        $date = $period->start_date->toDateString();

        $direct = $this->meals()->saveDailyTotal($mess, $a, $date, '2.5', $mess->tenant->owner);
        $derived = $this->meals()->saveDailyTotal($mess, $b, $date, null, $mess->tenant->owner, rawCount: '3', factor: '0.75');

        $this->assertSame('2.5000', $direct->total_units);
        $this->assertNull($direct->raw_count);
        $this->assertSame('2.2500', $derived->total_units);
        $this->assertSame('3.0000', $derived->raw_count);
        $this->assertSame('0.750000', $derived->conversion_factor);
        $this->assertSame(0, $direct->items()->count());
    }

    public function test_second_save_updates_the_same_entry_and_db_rejects_duplicates(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $t = $this->mealTypeIds($mess);
        $date = $period->start_date->toDateString();

        $first = $this->meals()->saveIndividual($mess, $member, $date, [$t['lunch'] => 1], $mess->tenant->owner);
        $second = $this->meals()->saveIndividual($mess, $member, $date, [$t['lunch'] => 1, $t['dinner'] => 1], $mess->tenant->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('2.0000', $second->total_units);
        $this->assertSame(1, MealEntry::count());

        $this->expectException(QueryException::class);
        MealEntry::create($first->only(['tenant_id', 'mess_id', 'period_id', 'member_id', 'meal_date', 'tracking_mode', 'status']));
    }

    public function test_modes_cannot_be_mixed_on_the_same_day(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $t = $this->mealTypeIds($mess);
        $date = $period->start_date->toDateString();

        $entry = $this->meals()->saveIndividual($mess, $member, $date, [$t['lunch'] => 1], $mess->tenant->owner);

        try {
            $this->meals()->saveDailyTotal($mess, $member, $date, '2', $mess->tenant->owner);
            $this->fail('Mode switch was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tracking_mode', $e->errors());
        }

        // After voiding, the day can be re-recorded in the other mode.
        app(MealApprovalService::class)->void($entry, $mess->tenant->owner, 'wrong mode');
        $redo = $this->meals()->saveDailyTotal($mess, $member, $date, '2', $mess->tenant->owner);
        $this->assertSame('daily_total', $redo->tracking_mode->value);
        $this->assertSame($entry->id, $redo->id);
    }

    public function test_changing_a_meal_factor_does_not_alter_recorded_entries(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $breakfast = $mess->mealTypes()->where('code', 'breakfast')->first();
        $date = $period->start_date->toDateString();

        $old = $this->meals()->saveIndividual($mess, $member, $date, [$breakfast->id => 2], $mess->tenant->owner, approve: true);
        app(MealTypeService::class)->update($breakfast, ['unit_factor' => '0.75']);
        $new = $this->meals()->saveIndividual($mess, $member, $period->start_date->copy()->addDay()->toDateString(), [$breakfast->id => 2], $mess->tenant->owner);

        $this->assertSame('1.0000', $old->fresh()->total_units);
        $this->assertSame('0.500000', $old->items()->first()->unit_factor_snapshot);
        $this->assertSame('1.5000', $new->total_units);
    }

    public function test_guest_meal_units_and_charge(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $sponsor = $this->memberOf($mess, Role::MEMBER);
        $breakfast = $mess->mealTypes()->where('code', 'breakfast')->first();

        $guest = app(GuestMealService::class)->create($mess, [
            'guest_name' => 'Cousin', 'meal_date' => $period->start_date->toDateString(), 'quantity' => '3',
            'meal_type_id' => $breakfast->id, 'sponsor_member_id' => $sponsor->id, 'charge_amount' => '90',
        ], $mess->tenant->owner);

        $this->assertSame('1.5000', $guest->weighted_units);
        $this->assertSame('90.00', $guest->charge_amount);
        $this->assertSame('unpaid', $guest->payment_status->value);

        $noType = app(GuestMealService::class)->create($mess, [
            'guest_name' => 'Friend', 'meal_date' => $period->start_date->toDateString(), 'quantity' => '2',
        ], $mess->tenant->owner);
        $this->assertSame('2.0000', $noType->weighted_units);
        $this->assertSame('not_applicable', $noType->payment_status->value);
    }

    public function test_guest_sponsor_from_another_mess_is_rejected(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        [$other] = $this->messWithOpenPeriod();
        $foreign = $this->memberOf($other, Role::MEMBER);

        $this->expectException(ValidationException::class);
        app(GuestMealService::class)->create($mess, [
            'guest_name' => 'X', 'meal_date' => $period->start_date->toDateString(), 'quantity' => '1', 'sponsor_member_id' => $foreign->id,
        ], $mess->tenant->owner);
    }

    public function test_mid_month_joining_blocks_meals_before_join_date(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod('2026-03-01', '2026-03-31');
        $member = $this->memberOf($mess, Role::MEMBER);
        $member->update(['joined_at' => '2026-03-15']);
        $t = $this->mealTypeIds($mess);

        try {
            $this->meals()->saveIndividual($mess, $member, '2026-03-14', [$t['lunch'] => 1], $mess->tenant->owner);
            $this->fail('Meal before joining was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('member', $e->errors());
        }

        $this->assertNotNull($this->meals()->saveIndividual($mess, $member, '2026-03-15', [$t['lunch'] => 1], $mess->tenant->owner));
        $this->assertSame(0, $mess->members()->effectiveOn('2026-03-14')->count());
        $this->assertSame(1, $mess->members()->effectiveOn('2026-03-15')->count());
    }

    public function test_mid_month_leaving_blocks_later_meals(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod('2026-03-01', '2026-03-31');
        $member = $this->memberOf($mess, Role::MEMBER);
        $member->update(['joined_at' => '2026-03-01']);
        $t = $this->mealTypeIds($mess);
        $owner = $mess->tenant->owner;
        $memberships = app(MembershipService::class);

        $this->meals()->saveIndividual($mess, $member, '2026-03-20', [$t['lunch'] => 1], $owner);

        // Cannot leave before a recorded meal.
        try {
            $memberships->changeStatus($member, MembershipStatus::Left, '2026-03-10', $owner);
            $this->fail('Leaving before recorded meals was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('left_at', $e->errors());
        }

        $memberships->changeStatus($member, MembershipStatus::Left, '2026-03-20', $owner);
        $member->refresh();

        $this->assertTrue($member->isEffectiveOn('2026-03-20'));
        $this->assertFalse($member->isEffectiveOn('2026-03-21'));

        $this->expectException(ValidationException::class);
        $this->meals()->saveIndividual($mess, $member, '2026-03-21', [$t['lunch'] => 1], $owner);
    }

    public function test_suspended_member_cannot_receive_meals(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        app(MembershipService::class)->changeStatus($member, MembershipStatus::Suspended, null, $mess->tenant->owner);

        $this->expectException(ValidationException::class);
        $this->meals()->saveDailyTotal($mess, $member->fresh(), $period->start_date->toDateString(), '1', $mess->tenant->owner);
    }

    public function test_non_open_periods_reject_entries_and_closed_ones_reject_approvals(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $date = $period->start_date->toDateString();
        $entry = $this->meals()->saveDailyTotal($mess, $member, $date, '2', $mess->tenant->owner);

        $period->update(['status' => 'reviewing']);
        try {
            $this->meals()->saveDailyTotal($mess, $member, $date, '3', $mess->tenant->owner);
            $this->fail('Entry accepted in reviewing period.');
        } catch (ValidationException) {
        }
        // Approval is still possible while reviewing.
        app(MealApprovalService::class)->approve($entry, $mess->tenant->owner);

        $period->update(['status' => 'finalized']);
        $this->expectException(ValidationException::class);
        app(MealApprovalService::class)->void($entry->fresh(), $mess->tenant->owner, 'late');
    }

    public function test_meals_outside_any_period_are_rejected(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);

        $this->expectException(ValidationException::class);
        $this->meals()->saveDailyTotal($mess, $member, $period->end_date->copy()->addDay()->toDateString(), '1', $mess->tenant->owner);
    }

    public function test_approval_rejection_and_correction_are_audited(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $owner = $mess->tenant->owner;
        $date = $period->start_date->toDateString();
        $approvals = app(MealApprovalService::class);

        $entry = $this->meals()->saveDailyTotal($mess, $member, $date, '2', $owner);
        $approvals->approve($entry, $owner);
        $this->assertSame(EntryStatus::Approved, $entry->fresh()->status);

        // Approving twice is rejected.
        try {
            $approvals->approve($entry->fresh(), $owner);
            $this->fail('Double approval accepted.');
        } catch (ValidationException) {
        }

        // Correcting an approved entry sends it back for approval.
        $corrected = $this->meals()->saveDailyTotal($mess, $member, $date, '3', $owner);
        $this->assertSame(EntryStatus::Submitted, $corrected->status);
        $this->assertNull($corrected->approved_by);
        $this->assertTrue(AuditLog::where('action', 'meal_entry.corrected')->where('auditable_id', $entry->id)->exists());

        $approvals->reject($corrected, $owner, 'Did not eat dinner');
        $this->assertSame('Did not eat dinner', $corrected->fresh()->rejection_reason);
        $this->assertTrue(AuditLog::where('action', 'MealEntry.rejected')->exists());
    }

    public function test_zero_meals_for_a_new_day_create_no_row(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $t = $this->mealTypeIds($mess);

        $this->assertNull($this->meals()->saveIndividual($mess, $member, $period->start_date->toDateString(), [$t['lunch'] => 0], $mess->tenant->owner));
        $this->assertSame(0, MealEntry::count());
    }
}

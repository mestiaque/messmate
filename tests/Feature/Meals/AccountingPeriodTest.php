<?php

namespace Tests\Feature\Meals;

use App\Enums\EntryStatus;
use App\Enums\PeriodMode;
use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\Mess;
use App\Models\Role;
use App\Services\Periods\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AccountingPeriodService
    {
        return app(AccountingPeriodService::class);
    }

    public function test_calendar_period_covers_the_whole_month(): void
    {
        $mess = Mess::factory()->create();
        $period = $this->service()->create($mess, $mess->tenant->owner, PeriodMode::Calendar, '2026-02', null, null);

        $this->assertSame('2026-02-01', $period->start_date->toDateString());
        $this->assertSame('2026-02-28', $period->end_date->toDateString());
        $this->assertSame('February 2026', $period->name);
        $this->assertSame(PeriodStatus::Draft, $period->status);
    }

    public function test_custom_period_with_end_before_start_is_rejected(): void
    {
        $mess = Mess::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service()->create($mess, $mess->tenant->owner, PeriodMode::Custom, null, '2026-03-10', '2026-03-01');
    }

    public function test_overlapping_periods_are_rejected_but_other_messes_are_independent(): void
    {
        $mess = Mess::factory()->create();
        $other = Mess::factory()->create(['tenant_id' => $mess->tenant_id]);
        $owner = $mess->tenant->owner;

        $this->service()->create($mess, $owner, PeriodMode::Calendar, '2026-03', null, null);
        $this->service()->create($other, $owner, PeriodMode::Calendar, '2026-03', null, null);
        $this->service()->create($mess, $owner, PeriodMode::Custom, null, '2026-04-01', '2026-04-15');

        foreach ([['2026-03-31', '2026-04-05'], ['2026-02-20', '2026-03-01'], ['2026-04-10', '2026-04-10']] as [$start, $end]) {
            try {
                $this->service()->create($mess, $owner, PeriodMode::Custom, null, $start, $end);
                $this->fail("Overlap {$start}–{$end} was accepted.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('start_date', $e->errors());
            }
        }

        // Adjacent ranges are fine.
        $this->service()->create($mess, $owner, PeriodMode::Custom, null, '2026-04-16', '2026-04-30');
        $this->assertSame(3, $mess->periods()->count());
    }

    public function test_lifecycle_transitions_follow_the_allowed_order(): void
    {
        $mess = Mess::factory()->create();
        $owner = $mess->tenant->owner;
        $period = $this->service()->create($mess, $owner, PeriodMode::Calendar, '2026-05', null, null);

        try {
            $this->service()->transition($period, PeriodStatus::Finalized, $owner);
            $this->fail('Draft period was finalized directly.');
        } catch (ValidationException) {
        }

        foreach ([PeriodStatus::Open, PeriodStatus::Reviewing] as $status) {
            $period = $this->service()->transition($period, $status, $owner);
        }

        // Finalizing is only possible through the settlement.
        app(\App\Services\Settlement\SettlementService::class)->finalize($period, $owner);
        $period = $this->service()->transition($period->fresh(), PeriodStatus::Locked, $owner);

        $this->assertSame($owner->id, $period->opened_by);
        $this->assertSame($owner->id, $period->finalized_by);
        $this->assertNotNull($period->locked_at);
    }

    public function test_finalizing_is_blocked_while_items_are_pending(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $period->mealEntries()->create([
            'tenant_id' => $mess->tenant_id, 'mess_id' => $mess->id, 'member_id' => $member->id,
            'meal_date' => $period->start_date, 'tracking_mode' => 'daily_total', 'total_units' => 2, 'status' => EntryStatus::Submitted,
        ]);
        $owner = $mess->tenant->owner;

        $this->service()->transition($period, PeriodStatus::Reviewing, $owner);

        try {
            app(\App\Services\Settlement\SettlementService::class)->finalize($period->fresh(), $owner);
            $this->fail('Finalized with pending meal entries.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('pending', implode(' ', $e->errors()['settlement']));
        }
    }

    public function test_reopening_requires_permission_and_reason_and_is_audited(): void
    {
        $mess = Mess::factory()->create();
        $period = AccountingPeriod::factory()->for($mess)->state(['tenant_id' => $mess->tenant_id])->status('finalized')->create();
        $manager = $this->memberOf($mess, Role::MANAGER);
        $admin = $this->memberOf($mess, Role::MESS_ADMIN);
        $url = "/t/{$mess->tenant->slug}/m/{$mess->slug}/periods/{$period->id}/reopen";

        $this->actingAs($manager->user)->post($url, ['reason' => 'Missed bazar entry'])->assertForbidden();
        $this->actingAs($admin->user)->post($url, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin->user)->post($url, ['reason' => 'Missed bazar entry'])->assertSessionHasNoErrors();

        $this->assertSame(PeriodStatus::Open, $period->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'period.reopened', 'auditable_id' => $period->id]);
        $this->assertStringContainsString('Missed bazar entry', \App\Models\AuditLog::where('action', 'period.reopened')->first()->new_values['reason']);
    }

    public function test_period_screens_respect_permissions(): void
    {
        $mess = Mess::factory()->create();
        $member = $this->memberOf($mess, Role::MEMBER);
        $manager = $this->memberOf($mess, Role::MANAGER);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}/periods";

        $this->actingAs($member->user)->get($base)->assertOk()->assertDontSee('New period');
        $this->actingAs($member->user)->post($base, ['period_type' => 'calendar', 'month' => '2026-06'])->assertForbidden();
        $this->actingAs($manager->user)->post($base, ['period_type' => 'calendar', 'month' => '2026-06'])->assertSessionHasNoErrors();
        $this->assertSame(1, $mess->periods()->count());
    }

    public function test_period_from_another_mess_cannot_be_transitioned(): void
    {
        $mess = Mess::factory()->create();
        $foreign = AccountingPeriod::factory()->status('draft')->create();

        $this->actingAs($mess->tenant->owner)
            ->put("/t/{$mess->tenant->slug}/m/{$mess->slug}/periods/{$foreign->id}/status", ['status' => 'open'])
            ->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Portal;

use App\Models\Role;
use App\Services\Reports\ReportService;
use Tests\Feature\Settlement\SettlementTestCase;

class ReportTest extends SettlementTestCase
{
    private function base(): string
    {
        return "/t/{$this->mess->tenant->slug}/m/{$this->mess->slug}/reports";
    }

    public function test_every_report_renders_and_exports_for_finance_users(): void
    {
        $accountant = $this->memberOf($this->mess, Role::ACCOUNTANT);
        $a = $this->member();
        $this->units($a, '3');
        $this->food('300', paidBy: $a);
        $this->deposit($a, '100');
        $this->finalize();

        $this->actingAs($accountant->user)->get($this->base())->assertOk()->assertSee('Monthly meals by member');

        foreach (array_keys(ReportService::TYPES) as $type) {
            $query = match ($type) {
                'daily-meals' => '?date=2026-11-05',
                'member-ledger' => "?member={$a->id}",
                'outstanding' => '',
                default => "?period={$this->period->id}",
            };

            $this->actingAs($accountant->user)->get("{$this->base()}/{$type}{$query}")->assertOk();
            $csv = $this->actingAs($accountant->user)->get("{$this->base()}/{$type}{$query}".($query ? '&' : '?').'format=csv');
            $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->streamedContent());
        }
    }

    public function test_report_values_come_from_the_database(): void
    {
        $owner = $this->owner();
        $a = $this->member();
        $this->deposit($a, '1500');
        $this->deposit($a, '250', '2026-11-12');

        $csv = $this->actingAs($owner)->get("{$this->base()}/deposits?period={$this->period->id}&format=csv")->streamedContent();

        $this->assertStringContainsString('1500.00', $csv);
        $this->assertStringContainsString('Total,,,,,1750.00', $csv);

        $settlementCsv = $this->actingAs($owner)->get("{$this->base()}/settlement?period={$this->period->id}")->assertOk();
        $settlementCsv->assertSee('not finalized');
    }

    public function test_csv_neutralises_formula_injection(): void
    {
        $a = $this->member();
        $a->update(['full_name' => '=HYPERLINK("http://evil.test","x")']);
        $this->deposit($a, '10');

        $csv = $this->actingAs($this->owner())->get("{$this->base()}/deposits?period={$this->period->id}&format=csv")->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_members_get_only_their_own_ledger(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->deposit($a, '400');

        $this->actingAs($a->user)->get("{$this->base()}/member-ledger?member={$a->id}")->assertOk()->assertSee('400.00');
        $this->actingAs($a->user)->get("{$this->base()}/member-ledger?member={$b->id}")->assertForbidden();
        $this->actingAs($a->user)->get("{$this->base()}/bazar?period={$this->period->id}")->assertForbidden();
        $this->actingAs($a->user)->get("{$this->base()}/outstanding")->assertForbidden();
        $this->actingAs($a->user)->get($this->base())->assertForbidden();
        $this->actingAs($this->owner())->get("{$this->base()}/not-a-report")->assertNotFound();
    }

    public function test_reports_cannot_read_another_mess(): void
    {
        [$other] = $this->messWithOpenPeriod();
        $foreign = $this->memberOf($other, Role::MEMBER);

        $this->actingAs($this->owner())->get("{$this->base()}/member-ledger?member={$foreign->id}")->assertNotFound();
        $this->actingAs($this->owner())->get("{$this->base()}/bazar?period={$other->periods()->first()->id}")->assertNotFound();
    }
}

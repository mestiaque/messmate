<?php

namespace Tests\Feature\Portal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Settlement\SettlementTestCase;

/**
 * Guards against N+1 queries: list pages must not issue more queries as rows grow.
 */
class QueryCountTest extends SettlementTestCase
{
    private function queriesFor(string $path): int
    {
        Cache::flush();
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->actingAs($this->owner())->get($path)->assertOk();
        DB::flushQueryLog();

        return $count;
    }

    private function addRows(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $m = $this->member();
            $this->units($m, '1', '2026-11-0'.(($i % 9) + 1));
            $this->deposit($m, '100');
            $this->food('50', '2026-11-02', $m);
        }
    }

    public function test_list_pages_have_bounded_query_counts(): void
    {
        $base = "/t/{$this->mess->tenant->slug}/m/{$this->mess->slug}";
        $pages = ['members', 'bazar', 'deposits', 'expenses', 'ledger', 'meals/approvals', "reports/deposits?period={$this->period->id}", "reports/monthly-meals?period={$this->period->id}"];

        $this->addRows(2);
        // Warm-up pass: first requests do one-off work (e.g. creating system accounts).
        array_map(fn ($p) => $this->queriesFor("{$base}/{$p}"), $pages);
        $small = array_map(fn ($p) => $this->queriesFor("{$base}/{$p}"), $pages);

        $this->addRows(8);
        $large = array_map(fn ($p) => $this->queriesFor("{$base}/{$p}"), $pages);

        foreach ($pages as $i => $page) {
            $this->assertSame($small[$i], $large[$i], "{$page}: {$small[$i]} queries with 2 rows but {$large[$i]} with 10 (N+1).");
        }
    }
}

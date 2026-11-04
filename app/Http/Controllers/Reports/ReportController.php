<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Billing\SubscriptionService;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        $available = array_filter(ReportService::TYPES, fn ($t) => $request->user()->can($t[2], $mess));
        abort_unless($available, 403);

        return view('reports.index', ['mess' => $mess, 'types' => $available]);
    }

    public function show(Request $request, Tenant $tenant, Mess $mess, string $type, ReportService $reports, AccountingPeriodService $periods): View|StreamedResponse
    {
        [$label, $needs, $ability] = ReportService::TYPES[$type] ?? abort(404);
        $member = $needs === 'member' && $request->filled('member') ? $mess->members()->findOrFail($request->integer('member')) : null;

        // Members may read their own ledger; everything else needs the report's permission.
        $ownLedger = $type === 'member-ledger' && $member && $member->user_id === $request->user()->id;
        abort_unless($ownLedger || $request->user()->can($ability, $mess), 403);

        $period = null;
        if ($needs === 'period') {
            $period = $request->filled('period')
                ? $mess->periods()->findOrFail($request->integer('period'))
                : ($periods->periodFor($mess, now($mess->timezone)) ?? $mess->periods()->latest('start_date')->first());
        }

        $date = $needs === 'date' ? ($request->date('date')?->toDateString() ?? now($mess->timezone)->toDateString()) : null;
        $ready = match ($needs) { 'period' => (bool) $period, 'member' => (bool) $member, default => true };
        $report = $ready ? $reports->build($type, $mess, $period, $date, $member) : null;

        if ($report && $request->query('format') === 'csv') {
            abort_unless(app(SubscriptionService::class)->hasFeature($tenant, 'reports_export'), 403, 'CSV export is not included in your plan.');

            return $this->csv($report, Str::slug($mess->name.' '.$report['title'].' '.$report['subtitle']));
        }

        return view('reports.show', [
            'mess' => $mess,
            'type' => $type,
            'label' => $label,
            'needs' => $needs,
            'report' => $report,
            'period' => $period,
            'date' => $date,
            'member' => $member,
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name']),
            'members' => $needs === 'member' ? $mess->members()->orderBy('member_code')->get(['id', 'member_code', 'full_name']) : collect(),
            'canExport' => app(SubscriptionService::class)->hasFeature($tenant, 'reports_export'),
        ]);
    }

    /**
     * UTF-8 CSV with a BOM so Excel shows Bangla names correctly. Text cells that
     * start with a formula character are prefixed to block CSV formula injection.
     */
    private function csv(array $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$report['title'], $report['subtitle']]);
            fputcsv($out, array_values($report['columns']));

            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($key) => $this->safeCell($row[$key] ?? '', in_array($key, $report['numeric'], true)), array_keys($report['columns'])));
            }

            if ($report['totals']) {
                fputcsv($out, array_map(fn ($key, $i) => $i === 0 ? 'Total' : ($report['totals'][$key] ?? ''), array_keys($report['columns']), array_keys(array_keys($report['columns']))));
            }

            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(string $value, bool $numeric): string
    {
        if ($numeric && is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}

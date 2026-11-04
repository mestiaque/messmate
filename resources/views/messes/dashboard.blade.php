@extends('layouts.app')
@section('title', $mess->name)
@section('content')
    @php($fmt = fn ($v, $d = 2) => number_format((float) $v, $d))
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $mess->name }}</h1>
            <div class="small text-muted">
                @if ($member){{ $member->member_code }} · {{ $member->roles->pluck('name')->join(', ') }}@else Organization owner @endif
            </div>
        </div>
        @if ($periods->isNotEmpty())
            <form method="GET"><select name="period" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Period">
                @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->name }}</option>@endforeach
            </select></form>
        @endif
    </div>

    @if (! $summary)
        <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-plus fs-1 d-block mb-2"></i>
            {{ $activeMembers }} active member(s). Create an <a href="{{ route('periods.index') }}">accounting period</a> to start recording meals and money.
        </div></div>
    @else
        <div class="row g-3 mb-3">
            @foreach (array_filter([
                ['Active members', $summary['active_members'], true],
                ['Meal units', rtrim(rtrim($summary['total_units'], '0'), '.') ?: '0', true],
                ['Estimated meal rate', $summary['estimated_rate'] !== null ? $fmt($summary['estimated_rate']) : '—', true],
                ['Bazar', $fmt($summary['bazar_total']), $canFinance],
                ['Other expenses', $fmt($summary['other_expenses']), $canFinance],
                ['Deposits', $fmt($summary['deposits']), $canFinance],
                ['Members owe', $fmt($summary['outstanding']), $canFinance],
            ], fn ($c) => $c[2]) as [$label, $value])
                <div class="col-6 col-md-4 col-xl"><div class="card border-0 shadow-sm stat-card h-100"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div><div class="value">{{ $value }}</div>
                </div></div></div>
            @endforeach
        </div>
        @if ($summary['rate_problem'])<div class="alert alert-warning small py-2">{{ $summary['rate_problem'] }}</div>@endif

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="fw-semibold mb-1">Meal units per day</div>
                    <div style="height: 220px"><canvas id="chart-units" aria-label="Meal units per day" role="img"></canvas></div>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="fw-semibold mb-1">Approved spending per day <span class="small text-muted fw-normal">(bazar + expenses)</span></div>
                    <div style="height: 220px"><canvas id="chart-spend" aria-label="Approved spending per day" role="img"></canvas></div>
                </div></div>
            </div>
        </div>
        <details class="mb-3 small">
            <summary class="text-muted">Show chart data as a table</summary>
            <div class="table-responsive mt-2"><table class="table table-sm bg-white">
                <thead><tr><th>Date</th><th class="text-end">Units</th><th class="text-end">Spending</th></tr></thead>
                @foreach ($summary['trend']['labels'] as $i => $day)
                    <tr><td>{{ $day }}</td><td class="text-end">{{ $summary['trend']['units'][$i] === null ? '—' : (rtrim(rtrim($summary['trend']['units'][$i], '0'), '.') ?: '0') }}</td><td class="text-end">{{ $summary['trend']['spend'][$i] === null ? '—' : $fmt($summary['trend']['spend'][$i]) }}</td></tr>
                @endforeach
            </table></div>
        </details>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Pending approvals</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($summary['pending'] as $label => $count)
                            <li class="list-group-item d-flex justify-content-between">{{ $label }}
                                <span class="badge rounded-pill text-bg-{{ $count ? 'warning' : 'light border' }}">{{ $count }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @if ($canFinance)
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Recent transactions</span><a href="{{ route('ledger.index') }}" class="small">Ledger</a></div>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            @forelse ($summary['recent'] as $tx)
                                <tr><td class="small text-muted text-nowrap">{{ $tx['number'] }}</td><td class="small text-nowrap">{{ $tx['date'] }}</td>
                                    <td class="small">{{ $tx['description'] }} @if ($tx['status'] === 'reversed')<span class="badge text-bg-dark">reversed</span>@endif</td>
                                    <td class="text-end small">{{ $fmt($tx['amount']) }}</td></tr>
                            @empty
                                <tr><td class="text-muted small p-3">Nothing posted yet.</td></tr>
                            @endforelse
                        </table></div>
                    </div>
                </div>
            @endif
        </div>
        <p class="small text-muted mt-2 mb-0">Figures refresh within {{ \App\Services\Dashboard\MessDashboardService::CACHE_SECONDS }} seconds. The meal rate is an estimate from approved records until the period is settled.</p>

        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (() => {
                    const trend = @json($summary['trend']);
                    const labels = trend.labels.map(d => d.slice(8)); // day of month
                    const series = '#2a78d6';
                    const ink = '#6c757d', grid = 'rgba(0,0,0,.06)';
                    const axes = {
                        x: { grid: { display: false }, ticks: { color: ink, maxRotation: 0, autoSkipPadding: 8 } },
                        y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { color: ink, maxTicksLimit: 5 } },
                    };
                    const base = {
                        responsive: true, maintainAspectRatio: false, animation: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { title: items => trend.labels[items[0].dataIndex] } } },
                        scales: axes,
                    };
                    new Chart(document.getElementById('chart-units'), {
                        type: 'line',
                        data: { labels, datasets: [{ data: trend.units.map(v => v === null ? null : Number(v)), borderColor: series, backgroundColor: series, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0 }] },
                        options: base,
                    });
                    new Chart(document.getElementById('chart-spend'), {
                        type: 'bar',
                        data: { labels, datasets: [{ data: trend.spend.map(v => v === null ? null : Number(v)), backgroundColor: series, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 14 }] },
                        options: { ...base, plugins: { ...base.plugins, tooltip: { callbacks: { ...base.plugins.tooltip.callbacks, label: c => ' ' + c.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2 }) } } } },
                    });
                })();
            </script>
        @endpush
    @endif
@endsection

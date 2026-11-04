@extends('layouts.app')
@section('title', 'Settlement')
@section('content')
    @php($fmt = fn ($v, $d = 2) => number_format((float) $v, $d))
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Settlement{{ $period ? ': '.$period->name : '' }}
            @if ($final)<span class="badge text-bg-primary">Final v{{ $final->calculation_version }}</span>
            @elseif ($draft)<span class="badge text-bg-secondary">Draft v{{ $draft->calculation_version }}</span>
            @else<span class="badge text-bg-light border text-dark">Live preview</span>@endif
        </h1>
        @if ($periods->isNotEmpty())
            <form method="GET"><select name="period" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Period">
                @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->name }} ({{ $p->status->value }})</option>@endforeach
            </select></form>
        @endif
    </div>

    @if (! $period)
        <div class="text-muted">No periods yet.</div>
    @else
        @foreach ($result['errors'] as $error)<div class="alert alert-danger py-2 small mb-2">{{ $error }}</div>@endforeach
        @foreach ($result['warnings'] as $warning)<div class="alert alert-warning py-2 small mb-2">{{ $warning }}</div>@endforeach

        <div class="row g-3 mb-3">
            @foreach ([
                'Food pool' => $fmt($result['food_pool']['total']),
                'Weighted units' => rtrim(rtrim($result['units']['total'], '0'), '.') ?: '0',
                $result['strategy'] === 'equal' ? 'Food per member' : 'Meal rate' => $result['meal_rate'] !== null ? $fmt($result['meal_rate'], 2) : '—',
                'Other expenses' => $fmt($result['other_expenses']['total']),
                'Deposits' => $fmt($result['totals']['deposits']),
            ] as $label => $value)
                <div class="col-6 col-md"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div><div class="value">{{ $value }}</div>
                </div></div></div>
            @endforeach
        </div>

        @include('settlements._steps')

        @if ($canManage && ! $final)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                    <form method="POST" action="{{ route('settlements.draft') }}">@csrf <input type="hidden" name="period_id" value="{{ $period->id }}">
                        <button class="btn btn-outline-primary" @disabled($result['errors'] || ! in_array($period->status->value, ['open', 'reviewing']))>Save draft</button></form>
                    @if ($period->status->value === 'reviewing')
                        <form method="POST" action="{{ route('settlements.finalize') }}" onsubmit="return confirm('Finalize {{ $period->name }}? Member charges will be posted and the period locked for edits.')">@csrf
                            <input type="hidden" name="period_id" value="{{ $period->id }}">
                            <button class="btn btn-primary" @disabled($result['errors'])>Finalize settlement</button></form>
                    @elseif ($period->status->value === 'open')
                        @can('managePeriods', $mess)
                            <form method="POST" action="{{ route('periods.transition', ['period' => $period]) }}" onsubmit="return confirm('Start review of {{ $period->name }}? New meals and money entries are blocked while it is in review (you can move it back to open).')">
                                @csrf @method('PUT') <input type="hidden" name="status" value="reviewing">
                                <button class="btn btn-primary" @disabled($result['errors'])><i class="bi bi-arrow-right-circle"></i> Start review</button>
                            </form>
                        @else
                            <span class="small text-muted">A manager must move the period to review before it can be finalized.</span>
                        @endcan
                    @endif
                    <button class="btn btn-link btn-sm ms-auto" data-bs-toggle="collapse" data-bs-target="#policy">Calculation settings</button>
                </div>
                <div class="collapse border-top @if($errors->hasAny(['strategy', 'custom_rate'])) show @endif" id="policy">
                    <form method="POST" action="{{ route('settlements.settings') }}" class="card-body row g-2 align-items-end">
                        @csrf @method('PUT')
                        <div class="col-md-4"><label class="form-label small" for="strategy">Meal rate strategy</label>
                            <select id="strategy" name="strategy" class="form-select">
                                <option value="weighted" @selected($policy->strategy === 'weighted')>Weighted: food ÷ units (default)</option>
                                <option value="equal" @selected($policy->strategy === 'equal')>Equal: food ÷ members</option>
                                <option value="custom" @selected($policy->strategy === 'custom')>Fixed rate per unit</option>
                            </select></div>
                        <div class="col-md-2"><label class="form-label small" for="custom_rate">Fixed rate</label>
                            <input id="custom_rate" name="custom_rate" value="{{ old('custom_rate', $policy->customRate) }}" class="form-control" inputmode="decimal"></div>
                        <div class="col-md-4"><label class="form-label small" for="equal_split_basis">Equal-split expenses</label>
                            <select id="equal_split_basis" name="equal_split_basis" class="form-select">
                                <option value="members" @selected($policy->equalSplitBasis === 'members')>Same share for every member present</option>
                                <option value="days" @selected($policy->equalSplitBasis === 'days')>In proportion to days present</option>
                            </select></div>
                        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Save</button></div>
                    </form>
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th>Member</th><th class="text-end">Opening</th><th class="text-end">Units</th><th class="text-end">Meals</th><th class="text-end d-none d-lg-table-cell">Guests</th>
                        <th class="text-end">Other</th><th class="text-end">Deposits</th><th class="text-end d-none d-lg-table-cell">± Other</th><th class="text-end">Closing</th><th></th>
                    </tr></thead>
                    <tbody>
                    @forelse ($result['members'] as $memberId => $row)
                        @php($saved = $settlementsByMember->get($memberId))
                        <tr>
                            <td>{{ $row['name'] }}<div class="small text-muted">{{ $row['code'] }}{{ $row['eligible'] ? '' : ' · not present' }}</div></td>
                            <td class="text-end">{{ $fmt($row['opening_credit']) }}</td>
                            <td class="text-end">{{ rtrim(rtrim($row['meal_units'], '0'), '.') ?: '0' }}</td>
                            <td class="text-end">{{ $fmt($row['meal_cost']) }}</td>
                            <td class="text-end d-none d-lg-table-cell">{{ $fmt($row['guest_cost']) }}</td>
                            <td class="text-end">{{ $fmt($row['other_expense']) }}</td>
                            <td class="text-end">{{ $fmt($row['total_deposits']) }}</td>
                            <td class="text-end d-none d-lg-table-cell">{{ $fmt(bcsub($row['other_credits'], $row['other_debits'], 2)) }}</td>
                            <td class="text-end fw-semibold {{ $row['closing_credit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($row['closing_credit']) }}</td>
                            <td class="text-end">@if ($saved)<a href="{{ route('settlements.show', ['settlement' => $saved]) }}" class="btn btn-sm btn-outline-secondary">Statement</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No members in this period.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($result['members'])
                        <tfoot class="table-light"><tr class="fw-semibold">
                            <td>Total</td><td></td><td></td><td class="text-end">{{ $fmt(array_sum(array_column($result['members'], 'meal_cost'))) }}</td>
                            <td class="d-none d-lg-table-cell"></td><td class="text-end">{{ $fmt(array_sum(array_column($result['members'], 'other_expense'))) }}</td>
                            <td class="text-end">{{ $fmt($result['totals']['deposits']) }}</td><td class="d-none d-lg-table-cell"></td>
                            <td class="text-end">{{ $fmt($result['totals']['closing']) }}</td><td></td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                Positive closing = the mess owes the member; negative = the member owes the mess.
                Strategy: {{ $result['strategy'] }} · equal splits by {{ $result['policy']['equal_split_basis'] }}.
                @if ($result['other_expenses']['excluded']) Not charged to members: {{ collect($result['other_expenses']['excluded'])->map(fn ($e) => $e['title'].' '.$fmt($e['amount']))->join(', ') }}.@endif
            </div>
        </div>

        @if ($history->isNotEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Versions</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($history as $h)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>v{{ $h->calculation_version }} · {{ $h->status }} · rate {{ $fmt($h->calculated_meal_rate, 4) }}</span>
                            <span class="text-muted">{{ $h->finalized_at?->format('d M Y H:i') }}{{ $h->superseded_reason ? ' · superseded: '.$h->superseded_reason : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
@endsection

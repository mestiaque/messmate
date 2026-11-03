@extends('layouts.app')
@section('title', 'Period report')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Period report{{ $period ? ': '.$period->name : '' }}</h1>
        <div class="d-flex gap-2">
            @if ($periods->isNotEmpty())
                <form method="GET"><select name="period" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Period">
                    @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->name }}</option>@endforeach
                </select></form>
            @endif
            <button class="btn btn-sm btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>
    @if (! $period)
        <div class="text-muted">No periods yet.</div>
    @else
        @if (array_sum($pending))
            <div class="alert alert-warning small">Not included until approved: {{ $pending['bazar'] }} bazar, {{ $pending['expenses'] }} expense(s), {{ $pending['deposits'] }} deposit(s).</div>
        @endif
        <div class="row g-3 mb-3">
            @foreach (['Bazar total' => $bazarTotal, 'Food share of bazar' => $bazarFood, 'Bazar discounts' => $bazarDiscounts, 'Other expenses' => $expensesByCategory->sum('amount'), 'Deposits' => $depositsByMember->sum('amount')] as $label => $value)
                <div class="col-6 col-md"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div><div class="value">{{ number_format((float) $value, 2) }}</div>
                </div></div></div>
            @endforeach
        </div>
        <div class="row g-3">
            @foreach ([
                ['Bazar by category', $bazarByCategory, fn ($r) => $r->name.($r->is_food_related ? '' : ' (non-food)')],
                ['Bazar by purchaser', $bazarByPurchaser, fn ($r) => $r->full_name.' · '.$r->trips.' trip(s)'],
                ['Expenses by category', $expensesByCategory, fn ($r) => $r->name.' · '.$r->category_type],
                ['Deposits by member', $depositsByMember, fn ($r) => $r->member_code.' '.$r->full_name.' · '.$r->count],
            ] as [$title, $rows, $label])
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-semibold">{{ $title }}</div>
                        <table class="table table-sm mb-0">
                            @forelse ($rows as $r)
                                <tr><td>{{ $label($r) }}</td><td class="text-end">{{ number_format($r->amount, 2) }}</td></tr>
                            @empty
                                <tr><td class="text-muted">Nothing approved.</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

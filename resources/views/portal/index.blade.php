@extends('layouts.app')
@section('title', 'My portal')
@section('content')
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($units = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0')
    @php($e = $data['estimate'])
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Hi, {{ $member->full_name }}</h1>
            <div class="small text-muted">{{ $member->member_code }} · {{ $mess->name }}{{ $period ? ' · '.$period->name : '' }}</div>
        </div>
        @if ($settings['self_service_meals'])
            <a href="{{ route('portal.meals') }}" class="btn btn-primary"><i class="bi bi-egg-fried"></i> Enter my meals</a>
        @endif
    </div>

    @foreach ($data['announcements'] as $a)
        <div class="alert alert-info py-2"><strong>{{ $a->title }}</strong> — {{ $a->body }} <span class="small text-muted">({{ $a->published_at->diffForHumans() }})</span></div>
    @endforeach

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm stat-card h-100"><div class="card-body">
            <div class="text-muted small">Today's meals</div>
            @if ($data['today'])
                <div class="value">{{ $units($data['today']->total_units) }}</div>
                <div class="small">{{ $data['today']->items->map(fn ($i) => $i->mealType->name)->join(', ') ?: 'Daily total' }} · {{ $data['today']->status->value }}</div>
            @else
                <div class="value">—</div><div class="small text-muted">Nothing recorded</div>
            @endif
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm stat-card h-100"><div class="card-body">
            <div class="text-muted small">My units this period</div><div class="value">{{ $units($data['units']) }}</div>
            <div class="small text-muted">submitted + approved</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm stat-card h-100"><div class="card-body">
            <div class="text-muted small">Estimated meal rate</div><div class="value">{{ $data['rate'] !== null ? $fmt($data['rate']) : '—' }}</div>
            <div class="small text-muted">from approved records so far</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm stat-card h-100"><div class="card-body">
            <div class="text-muted small">Current balance</div>
            <div class="value {{ $data['balance'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($data['balance']) }}</div>
            <div class="small">{{ $data['balance'] < 0 ? 'You owe the mess' : ($data['balance'] > 0 ? 'The mess owes you' : 'Settled') }}</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">This period so far <span class="small text-muted fw-normal">(estimate)</span></div>
                @if ($e)
                    <table class="table table-sm mb-0">
                        <tr><td>Meal cost</td><td class="text-end">{{ $fmt($e['meal_cost']) }}</td></tr>
                        @if ($e['guest_cost'] > 0)<tr><td>Guests</td><td class="text-end">{{ $fmt($e['guest_cost']) }}</td></tr>@endif
                        <tr><td>Other expenses</td><td class="text-end">{{ $fmt($e['other_expense']) }}</td></tr>
                        <tr><td>Deposits</td><td class="text-end">{{ $fmt($e['total_deposits']) }}</td></tr>
                        <tr class="fw-semibold"><td>Estimated closing</td><td class="text-end {{ $e['closing_credit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($e['closing_credit']) }}</td></tr>
                    </table>
                @else
                    <div class="card-body text-muted small">No activity in this period yet.</div>
                @endif
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Statements</div>
                <div class="list-group list-group-flush">
                    @forelse ($data['statements'] as $s)
                        <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('settlements.show', ['settlement' => $s]) }}">
                            <span>{{ $s->period->name }}</span><span class="{{ $s->closing_credit < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($s->closing_credit) }}</span></a>
                    @empty
                        <div class="list-group-item text-muted small">No finalized statements yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a href="{{ route('meals.calendar') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3"></i> Meal calendar</a>
                <a href="{{ route('reports.show', ['type' => 'member-ledger', 'member' => $member->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text"></i> Full history</a>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Recent transactions</div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    @forelse ($data['transactions'] as $line)
                        <tr><td class="small text-nowrap">{{ $line->transaction->transaction_date->format('d M') }}</td><td class="small">{{ $line->transaction->description }}</td>
                            <td class="text-end small {{ $line->debit > 0 ? 'text-danger' : 'text-success' }}">{{ $line->debit > 0 ? '−'.$fmt($line->debit) : '+'.$fmt($line->credit) }}</td></tr>
                    @empty
                        <tr><td class="text-muted small p-3">No transactions yet.</td></tr>
                    @endforelse
                </table></div>
            </div>
        </div>
    </div>
@endsection

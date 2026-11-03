@extends('layouts.app')
@section('title', 'Guest meals')
@section('content')
    @php($fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Guest meals</h1>
        @if ($periods->isNotEmpty())
            <form method="GET"><select name="period" class="form-select" onchange="this.form.submit()">
                @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->name }}</option>@endforeach
            </select></form>
        @endif
    </div>

    @if ($canManage)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Record a guest meal</div>
            <div class="card-body">
                <form method="POST" action="{{ route('meals.guests.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3"><label class="form-label small" for="guest_name">Guest name</label><input id="guest_name" name="guest_name" value="{{ old('guest_name') }}" class="form-control" required></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="meal_date">Date</label><input id="meal_date" type="date" name="meal_date" value="{{ old('meal_date', now($mess->timezone)->toDateString()) }}" class="form-control" required></div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="meal_type_id">Meal</label>
                        <select id="meal_type_id" name="meal_type_id" class="form-select">
                            <option value="">Full unit (× 1)</option>
                            @foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('meal_type_id') == $type->id)>{{ $type->name }} (× {{ $fmt($type->unit_factor) }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-4 col-md-1"><label class="form-label small" for="quantity">Qty</label><input id="quantity" name="quantity" value="{{ old('quantity', 1) }}" class="form-control" inputmode="decimal" required></div>
                    <div class="col-8 col-md-2">
                        <label class="form-label small" for="sponsor_member_id">Sponsor</label>
                        <select id="sponsor_member_id" name="sponsor_member_id" class="form-select">
                            <option value="">None</option>
                            @foreach ($members as $m)<option value="{{ $m->id }}" @selected(old('sponsor_member_id') == $m->id)>{{ $m->full_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="charge_amount">Charge ({{ $mess->currency }})</label><input id="charge_amount" name="charge_amount" value="{{ old('charge_amount') }}" class="form-control" inputmode="decimal" placeholder="optional"></div>
                    <div class="col-md-10"><input name="notes" value="{{ old('notes') }}" class="form-control form-control-sm" placeholder="Notes (optional)"></div>
                    <div class="col-md-2 d-flex gap-2 align-items-center">
                        @if ($canApprove)
                            <input type="hidden" name="approve" value="0">
                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="approve" value="1" id="g-approve" checked><label class="form-check-label small" for="g-approve">Approve</label></div>
                        @endif
                        <button class="btn btn-primary ms-auto">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Guest</th><th>Meal</th><th class="text-end">Units</th><th>Sponsor</th><th class="text-end">Charge</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($guests ?? [] as $guest)
                    <tr>
                        <td class="small">{{ $guest->meal_date->format('d M') }}</td>
                        <td>{{ $guest->guest_name }}</td>
                        <td class="small">{{ $guest->mealType?->name ?? 'Full unit' }} × {{ $fmt($guest->quantity) }}</td>
                        <td class="text-end">{{ $fmt($guest->weighted_units) }}</td>
                        <td class="small">{{ $guest->sponsor?->full_name ?? '—' }}</td>
                        <td class="text-end small">{{ $guest->charge_amount !== null ? number_format($guest->charge_amount, 2).' · '.str_replace('_', ' ', $guest->payment_status->value) : '—' }}</td>
                        <td><span class="badge text-bg-{{ $guest->status->badge() }}">{{ $guest->status->value }}</span></td>
                        <td>@include('meals._void', ['type' => 'guest-meals', 'record' => $guest])</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No guest meals{{ $period ? ' in '.$period->name : '' }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($guests)<div class="mt-3">{{ $guests->links() }}</div>@endif
@endsection

@extends('layouts.app')
@section('title', 'My meals')
@section('narrow', '1')
@section('content')
    @php($fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0')
    @php($locked = $entry && $entry->status->value === 'approved')
    @php($open = $period && $period->status->acceptsEntries())
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">My meals</h1>
        <form method="GET" class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('portal.meals', ['date' => $date->copy()->subDay()->toDateString()]) }}" aria-label="Previous day"><i class="bi bi-chevron-left"></i></a>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" onchange="this.form.submit()">
            <a class="btn btn-outline-secondary" href="{{ route('portal.meals', ['date' => $date->copy()->addDay()->toDateString()]) }}" aria-label="Next day"><i class="bi bi-chevron-right"></i></a>
        </form>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="fw-semibold mb-2">{{ $date->format('l, d M Y') }}
                @if ($entry)<span class="badge text-bg-{{ $entry->status->badge() }}">{{ $entry->status->value }}</span>@endif</div>
            @if (! $open)
                <div class="alert alert-secondary small mb-0">{{ $period ? "Period \"{$period->name}\" is {$period->status->value}." : 'No open period covers this date.' }}</div>
            @elseif ($locked)
                <div class="alert alert-success small mb-0">Approved: {{ $fmt($entry->total_units) }} units. Ask a manager if this needs a change.</div>
            @else
                <form method="POST" action="{{ route('portal.meals.store') }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    @if ($mess->default_meal_tracking_mode->value === 'individual')
                        @php($q = $entry ? $entry->items->pluck('quantity', 'meal_type_id') : collect())
                        @foreach ($types as $type)
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <label for="m{{ $type->id }}">{{ $type->name }} <span class="text-muted small">× {{ $fmt($type->unit_factor) }}</span></label>
                                <input id="m{{ $type->id }}" type="number" min="0" max="10" step="1" name="meals[{{ $type->id }}]" value="{{ $fmt($q[$type->id] ?? ($entry ? 0 : ($type->default_enabled ? 1 : 0))) }}" class="form-control text-center" style="width: 5rem">
                            </div>
                        @endforeach
                    @else
                        <label class="form-label" for="units">Units for the day</label>
                        <input id="units" type="number" min="0" max="20" step="0.5" name="units" value="{{ $entry ? $fmt($entry->total_units) : '' }}" class="form-control" style="max-width: 8rem">
                    @endif
                    <button class="btn btn-primary mt-3">{{ $settings['require_approval'] ? 'Submit for approval' : 'Save' }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection

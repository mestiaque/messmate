@extends('layouts.app')
@section('title', 'Daily meals')
@section('content')
    @php($fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0')
    @php($writable = $period && $period->status->acceptsEntries() && $canManage)
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Daily meals</h1>
        <form method="GET" class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('meals.daily', ['date' => $date->copy()->subDay()->toDateString(), 'mode' => $mode->value]) }}" aria-label="Previous day"><i class="bi bi-chevron-left"></i></a>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" onchange="this.form.submit()" style="width: auto">
            <a class="btn btn-outline-secondary" href="{{ route('meals.daily', ['date' => $date->copy()->addDay()->toDateString(), 'mode' => $mode->value]) }}" aria-label="Next day"><i class="bi bi-chevron-right"></i></a>
            <select name="mode" class="form-select" onchange="this.form.submit()" style="width: auto">
                <option value="individual" @selected($mode->value === 'individual')>Individual meals</option>
                <option value="daily_total" @selected($mode->value === 'daily_total')>Daily total</option>
            </select>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
            <div class="text-muted small">Member units</div><div class="value">{{ $fmt($summary['member_units']) }}</div>
        </div></div></div>
        @foreach ($types as $type)
            @php($t = $summary['by_type']->get($type->id))
            <div class="col-6 col-md-2"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">{{ $type->name }}</div><div class="value">{{ $fmt($t->quantity ?? 0) }}</div>
            </div></div></div>
        @endforeach
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
            <div class="text-muted small">Guest units</div><div class="value">{{ $fmt($summary['guest_units']) }}</div>
        </div></div></div>
    </div>

    @if (! $period)
        <div class="alert alert-warning">No accounting period covers {{ $date->toDateString() }}. <a href="{{ route('periods.index') }}">Create one</a> to record meals.</div>
    @elseif (! $period->status->acceptsEntries())
        <div class="alert alert-secondary">Period "{{ $period->name }}" is {{ $period->status->value }}; meals for this date are read-only.</div>
    @endif

    <form method="POST" action="{{ route('meals.daily.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="tracking_mode" value="{{ $mode->value }}">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 2rem"><input type="checkbox" class="form-check-input" id="select-all" @disabled(! $writable) aria-label="Select all"></th>
                        <th>Member</th>
                        @if ($mode->value === 'individual')
                            @foreach ($types as $type)
                                <th class="text-center">{{ $type->name }}<div class="small text-muted fw-normal">× {{ $fmt($type->unit_factor) }}</div></th>
                            @endforeach
                        @else
                            <th>Units</th>
                            <th>or raw count</th>
                        @endif
                        <th class="text-end">Saved</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($members as $member)
                        @php($entry = $existing->get($member->id))
                        @php($conflict = $entry && $entry->tracking_mode !== $mode)
                        @php($quantities = $entry ? $entry->items->pluck('quantity', 'meal_type_id') : collect())
                        <tr @class(['table-warning' => $conflict])>
                            <td><input type="checkbox" class="form-check-input js-row" name="selected[]" value="{{ $member->id }}" @checked(! $conflict && $writable && in_array($member->id, old('selected', []))) @disabled($conflict || ! $writable) aria-label="Select {{ $member->full_name }}"></td>
                            <td>
                                <a href="{{ route('members.show', ['member' => $member]) }}" class="text-decoration-none">{{ $member->full_name }}</a>
                                <div class="small text-muted">{{ $member->member_code }}@if ($member->room_number) · Room {{ $member->room_number }}@endif</div>
                                @if ($conflict)
                                    <div class="small text-danger">Recorded as {{ $entry->tracking_mode->value === 'individual' ? 'individual meals' : 'daily total' }}; switch mode to edit.</div>
                                @endif
                            </td>
                            @if ($mode->value === 'individual')
                                @foreach ($types as $type)
                                    @php($default = $entry ? ($quantities[$type->id] ?? 0) : ($type->default_enabled ? 1 : 0))
                                    <td class="text-center">
                                        <input type="number" min="0" max="50" step="0.5" inputmode="decimal" name="meals[{{ $member->id }}][{{ $type->id }}]"
                                               value="{{ old("meals.{$member->id}.{$type->id}", $fmt($default)) }}" class="form-control form-control-sm text-center mx-auto js-input" style="width: 4.5rem" @disabled($conflict || ! $writable)>
                                    </td>
                                @endforeach
                            @else
                                <td><input type="number" min="0" max="100" step="0.25" inputmode="decimal" name="units[{{ $member->id }}]" value="{{ old("units.{$member->id}", $entry && ! $conflict ? $fmt($entry->total_units) : '') }}" class="form-control form-control-sm js-input" style="width: 6rem" @disabled($conflict || ! $writable)></td>
                                <td><input type="number" min="0" max="100" step="1" inputmode="numeric" name="raw_count[{{ $member->id }}]" value="{{ old("raw_count.{$member->id}", $entry && ! $conflict && $entry->raw_count !== null ? $fmt($entry->raw_count) : '') }}" class="form-control form-control-sm js-input" style="width: 6rem" @disabled($conflict || ! $writable)></td>
                            @endif
                            <td class="text-end">
                                @if ($entry)
                                    {{ $fmt($entry->total_units) }} <span class="badge text-bg-{{ $entry->status->badge() }}">{{ $entry->status->value }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No members are effective on this date.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($writable && $members->isNotEmpty())
                <div class="card-footer bg-white d-flex flex-wrap gap-3 align-items-center">
                    @if ($mode->value === 'daily_total')
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-nowrap" for="conversion_factor">Raw count factor</label>
                            <input id="conversion_factor" name="conversion_factor" value="{{ old('conversion_factor', '1') }}" class="form-control form-control-sm" style="width: 5rem" inputmode="decimal">
                        </div>
                    @endif
                    @if ($canApprove)
                        <div class="form-check">
                            <input type="hidden" name="approve" value="0">
                            <input class="form-check-input" type="checkbox" name="approve" value="1" id="approve" @checked(old('approve', true))>
                            <label class="form-check-label" for="approve">Approve immediately</label>
                        </div>
                    @endif
                    <span class="small text-muted ms-auto">Only checked rows are saved. Editing an approved entry sends it back for approval unless approved now.</span>
                    <button class="btn btn-primary">Save selected</button>
                </div>
            @endif
        </div>
    </form>
    <script>
        (() => {
            const all = document.getElementById('select-all');
            const rows = [...document.querySelectorAll('.js-row:not(:disabled)')];
            all?.addEventListener('change', () => rows.forEach(r => r.checked = all.checked));
            // Editing a row's inputs selects that row.
            document.querySelectorAll('.js-input').forEach(input => input.addEventListener('input', () => {
                const box = input.closest('tr').querySelector('.js-row');
                if (box && ! box.disabled) box.checked = true;
            }));
        })();
    </script>
@endsection

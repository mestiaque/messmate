@extends('layouts.app')
@section('title', $member->full_name)
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $member->full_name }} <span class="text-muted fs-6">{{ $member->member_code }}</span></h1>
            <div class="small text-muted">
                <span class="badge text-bg-{{ $member->isActive() ? 'success' : 'secondary' }}">{{ ucfirst($member->membership_status->value) }}</span>
                Joined {{ $member->joined_at->toDateString() }}@if ($member->left_at) · Left {{ $member->left_at->toDateString() }}@endif
                @if ($member->room_number) · Room {{ $member->room_number }}@endif
                · {{ $member->roles->pluck('name')->join(', ') ?: 'No role' }}
            </div>
        </div>
        @if ($canManage)
            <a href="{{ route('members.edit', ['member' => $member]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        @endif
    </div>

    @if ($canManage)
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="fw-semibold mb-2">Membership status</div>
                    @if ($member->membership_status->value === 'left')
                        <p class="text-muted small mb-0">This membership has ended. Add the person again to start a new membership.</p>
                    @else
                        <form method="POST" action="{{ route('members.status', ['member' => $member]) }}" class="row g-2 align-items-end">
                            @csrf @method('PUT')
                            <div class="col-sm-5">
                                <select name="status" class="form-select form-select-sm" onchange="this.form.querySelector('.left-at').classList.toggle('d-none', this.value !== 'left')">
                                    @foreach (['active', 'inactive', 'suspended', 'left'] as $status)
                                        <option value="{{ $status }}" @selected($member->membership_status->value === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4 left-at d-none"><input type="date" name="left_at" class="form-control form-control-sm" aria-label="Left on"></div>
                            <div class="col-sm-3"><button class="btn btn-sm btn-outline-primary w-100">Update</button></div>
                        </form>
                    @endif
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="fw-semibold mb-2">Login account</div>
                    @if ($member->user)
                        <p class="small mb-0">Linked to <strong>{{ $member->user->name }}</strong> ({{ $member->user->email ?? $member->user->phone }})</p>
                    @else
                        <form method="POST" action="{{ route('members.link', ['member' => $member]) }}" class="d-flex gap-2">
                            @csrf @method('PUT')
                            <input name="login" class="form-control form-control-sm" placeholder="Registered email or phone" required>
                            <button class="btn btn-sm btn-outline-primary text-nowrap">Link account</button>
                        </form>
                    @endif
                </div></div>
            </div>
        </div>
    @endif

    @if ($settlements->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Settlements</div>
            <div class="list-group list-group-flush">
                @foreach ($settlements as $s)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('settlements.show', ['settlement' => $s]) }}">
                        <span>{{ $s->period->name }} <span class="badge text-bg-light border">{{ str_replace('_', ' ', $s->status) }}</span></span>
                        <span class="fw-semibold {{ $s->closing_credit < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($s->closing_credit, 2) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold">Meal statement</span>
            @if ($periods->isNotEmpty())
                <form method="GET" class="d-flex gap-2">
                    <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach ($periods as $p)
                            <option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
        @if (! $period)
            <div class="card-body text-muted">No accounting periods yet.</div>
        @else
            <div class="card-body">
                <div class="row g-3">
                    @foreach (['Approved units' => $totals['approved_units'], 'Pending units' => $totals['pending_units'], 'Adjustments' => $totals['adjustment_units'], 'Sponsored guest units' => $totals['sponsored_guest_units'], 'Days with meals' => $totals['days']] as $label => $value)
                        <div class="col-6 col-md">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fs-5 fw-semibold">{{ is_string($value) ? rtrim(rtrim($value, '0'), '.') : $value }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="small text-muted mt-2 mb-0">Meal cost and balance appear after the settlement module (Phase 4).</p>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Meals</th><th class="text-end">Units</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry->meal_date->format('D, d M') }}</td>
                            <td class="small">
                                @if ($entry->tracking_mode->value === 'individual')
                                    {{ $entry->items->map(fn ($i) => $i->mealType->name.' ×'.rtrim(rtrim($i->quantity, '0'), '.'))->join(', ') }}
                                @else
                                    Daily total @if ($entry->raw_count) ({{ rtrim(rtrim($entry->raw_count, '0'), '.') }} × {{ rtrim(rtrim($entry->conversion_factor, '0'), '.') }}) @endif
                                @endif
                            </td>
                            <td class="text-end">{{ rtrim(rtrim($entry->total_units, '0'), '.') ?: '0' }}</td>
                            <td><span class="badge text-bg-{{ $entry->status->badge() }}">{{ ucfirst($entry->status->value) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No meals recorded in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($adjustments->isNotEmpty())
                <div class="card-body border-top">
                    <div class="fw-semibold small mb-2">Adjustments</div>
                    @foreach ($adjustments as $adj)
                        <div class="small">{{ $adj->adjustment_units > 0 ? '+' : '' }}{{ rtrim(rtrim($adj->adjustment_units, '0'), '.') }} units — {{ $adj->reason }} <span class="badge text-bg-{{ $adj->status->badge() }}">{{ $adj->status->value }}</span></div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endsection

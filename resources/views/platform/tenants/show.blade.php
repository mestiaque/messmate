@extends('layouts.app')
@section('title', $tenant->name)
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $tenant->name }} @include('platform.tenants._status')</h1>
        @if ($tenant->isActive())
            <form method="POST" action="{{ route('platform.tenants.suspend', $tenant->id) }}" onsubmit="return confirm('Suspend this organization? Its users will lose access.')">
                @csrf <button class="btn btn-outline-danger">Suspend</button>
            </form>
        @else
            <form method="POST" action="{{ route('platform.tenants.activate', $tenant->id) }}">
                @csrf <button class="btn btn-success">Activate</button>
            </form>
        @endif
    </div>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <dl class="mb-0">
                    <dt>Owner</dt><dd>{{ $tenant->owner->name }} ({{ $tenant->owner->email ?? $tenant->owner->phone }})</dd>
                    <dt>Slug</dt><dd>{{ $tenant->slug }}</dd>
                    <dt>Contact</dt><dd>{{ $tenant->email ?: '—' }} / {{ $tenant->phone ?: '—' }}</dd>
                    <dt>Defaults</dt><dd>{{ $tenant->default_currency }} · {{ $tenant->default_timezone }}</dd>
                    <dt>Users</dt><dd>{{ $tenant->users_count }}</dd>
                    <dt>Created</dt><dd class="mb-0">{{ $tenant->created_at->toDayDateTimeString() }}</dd>
                </dl>
            </div></div>
        </div>
        <div class="col-lg-8">
            @php($sub = app(\App\Services\Billing\SubscriptionService::class)->current($tenant))
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Subscription: {{ $sub->plan->name }} <span class="badge text-bg-{{ $sub->status->badge() }}">{{ $sub->status->value }}</span></div>
                <div class="card-body">
                    <p class="small mb-2">
                        @if ($sub->trial_ends_at) Trial ends {{ $sub->trial_ends_at->format('d M Y') }} · @endif
                        Paid until {{ $sub->current_period_end?->format('d M Y') ?? '—' }} · access {{ app(\App\Services\Billing\SubscriptionService::class)->access($tenant) }}
                    </p>
                    <form method="POST" action="{{ route('platform.tenants.subscription', $tenant->id) }}" class="row g-2 align-items-end">
                        @csrf @method('PUT')
                        <div class="col-md-5"><label class="form-label small" for="plan_id">Set plan</label>
                            <select id="plan_id" name="plan_id" class="form-select form-select-sm">@foreach (\App\Models\SubscriptionPlan::orderBy('sort_order')->get() as $p)<option value="{{ $p->id }}" @selected($p->id === $sub->plan_id)>{{ $p->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label small" for="until">Active until (blank = open-ended)</label><input id="until" type="date" name="until" class="form-control form-control-sm"></div>
                        <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Apply</button></div>
                    </form>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Messes ({{ $tenant->messes_count }})</div>
                <ul class="list-group list-group-flush">
                    @forelse ($messes as $mess)
                        <li class="list-group-item d-flex justify-content-between">{{ $mess->name }} <span class="text-muted small">{{ $mess->members_count }} members</span></li>
                    @empty
                        <li class="list-group-item text-muted">No messes.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')
@section('title', 'Platform settings')
@section('content')
    <h1 class="h4 mb-3">Platform settings</h1>
    <form method="POST" action="{{ route('platform.settings.update') }}" class="card border-0 shadow-sm" style="max-width: 640px"><div class="card-body">
        @csrf @method('PUT')
        <div class="mb-3"><label class="form-label" for="grace_days">Grace period after a missed renewal (days)</label>
            <input id="grace_days" name="grace_days" value="{{ old('grace_days', $graceDays) }}" class="form-control" style="max-width: 8rem" required></div>
        <div class="mb-3"><label class="form-label" for="default_trial_plan">Plan for new organizations (its trial days apply)</label>
            <select id="default_trial_plan" name="default_trial_plan" class="form-select">@foreach ($plans as $p)<option value="{{ $p->slug }}" @selected($trialPlan === $p->slug)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label" for="payment_instructions">Manual payment instructions shown to tenants</label>
            <textarea id="payment_instructions" name="payment_instructions" rows="3" class="form-control" placeholder="e.g. bKash merchant 01XXXXXXXXX, reference = your organization name">{{ old('payment_instructions', $instructions) }}</textarea></div>
        <p class="small text-muted">Enabled payment providers: {{ implode(', ', config('billing.providers')) }} (set <code>BILLING_PROVIDERS</code> in .env).</p>
        <button class="btn btn-primary">Save</button>
    </div></form>
@endsection

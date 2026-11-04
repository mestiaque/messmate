@extends('layouts.app')
@section('title', 'Plans')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Subscription plans</h1>
            <p class="small text-muted mb-0">Changes apply to every organization on the plan at once. Over-limit organizations keep their data but cannot add more.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#edit-plan-new"><i class="bi bi-plus-lg"></i> New plan</button>
    </div>

    <div class="collapse mb-3 @if($errors->any() && ! old('plan_id')) show @endif" id="edit-plan-new">
        <div class="card border-0 shadow-sm"><div class="card-header">New plan</div>@include('platform._plan-form', ['plan' => null])</div>
    </div>

    <div class="row g-3">
        @foreach ($plans as $plan)
            @php($open = $errors->any() && (int) old('plan_id') === $plan->id)
            <div class="col-md-6 col-xxl-3">
                <div class="card border-0 shadow-sm mm-plan @unless($plan->is_active) inactive @endunless">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold">{{ $plan->name }} <span class="text-muted small fw-normal">· {{ $plan->slug }}</span></div>
                                <div class="small text-muted">{{ $plan->description }}</div>
                            </div>
                            <span class="badge text-bg-{{ $plan->is_active ? 'success' : 'secondary' }}">{{ $plan->is_active ? 'Available' : 'Hidden' }}</span>
                        </div>
                        <div class="price my-2">
                            @if ($plan->isFree()) Free @else {{ number_format($plan->monthly_price, 0) }}<small> /month · {{ number_format($plan->yearly_price, 0) }} /year</small> @endif
                        </div>
                        <div class="limits mb-3">
                            <div><b>{{ $plan->max_messes ?? '∞' }}</b><span>Messes</span></div>
                            <div><b>{{ $plan->max_members_per_mess ?? '∞' }}</b><span>Members/mess</span></div>
                            <div><b>{{ $plan->max_users ?? '∞' }}</b><span>Users</span></div>
                        </div>
                        <ul class="list-unstyled small mb-3">
                            @foreach ($features as $key => $label)
                                <li class="mb-1 {{ $plan->hasFeature($key) ? '' : 'text-muted' }}"><i class="bi bi-{{ $plan->hasFeature($key) ? 'check-circle-fill text-success' : 'dash-circle' }} me-2"></i>{{ $label }}</li>
                            @endforeach
                            <li class="mb-1"><i class="bi bi-hourglass-split me-2 text-muted"></i>{{ $plan->trial_days ? $plan->trial_days.'-day trial' : 'No trial' }}</li>
                        </ul>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><i class="bi bi-buildings me-1"></i>{{ $plan->subscriptions_count }} organization(s)</span>
                            <button class="btn btn-sm btn-outline-secondary @unless($open) collapsed @endunless" data-bs-toggle="collapse" data-bs-target="#edit-plan-{{ $plan->id }}"><i class="bi bi-pencil"></i> Edit</button>
                        </div>
                    </div>
                    <div class="collapse border-top @if($open) show @endif" id="edit-plan-{{ $plan->id }}">@include('platform._plan-form', ['plan' => $plan])</div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

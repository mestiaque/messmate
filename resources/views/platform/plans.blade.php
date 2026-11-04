@extends('layouts.app')
@section('title', 'Plans')
@section('content')
    <h1 class="h4 mb-1">Subscription plans</h1>
    <p class="small text-muted">Empty limit = unlimited. Limits apply to all tenants on the plan immediately; nothing is deleted when a tenant is over a limit, they just cannot add more.</p>
    @foreach ([...$plans, null] as $plan)
        @php($f = $plan ? 'plan-'.$plan->id : 'plan-new')
        <form method="POST" action="{{ $plan ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" class="card border-0 shadow-sm mb-3">
            @csrf @if ($plan) @method('PUT') @endif
            <div class="card-header bg-white fw-semibold">{{ $plan?->name ?? 'New plan' }}</div>
            <div class="card-body row g-2">
                <div class="col-md-3"><label class="form-label small">Name</label><input name="name" value="{{ $plan?->name }}" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">Slug</label><input name="slug" value="{{ $plan?->slug }}" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">Monthly</label><input name="monthly_price" value="{{ $plan?->monthly_price ?? 0 }}" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">Yearly</label><input name="yearly_price" value="{{ $plan?->yearly_price ?? 0 }}" class="form-control form-control-sm" required></div>
                <div class="col-md-1"><label class="form-label small">Trial days</label><input name="trial_days" value="{{ $plan?->trial_days ?? 0 }}" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">Order</label><input name="sort_order" value="{{ $plan?->sort_order ?? 0 }}" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label small">Max messes</label><input name="max_messes" value="{{ $plan?->max_messes }}" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label small">Members / mess</label><input name="max_members_per_mess" value="{{ $plan?->max_members_per_mess }}" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label small">Max users</label><input name="max_users" value="{{ $plan?->max_users }}" class="form-control form-control-sm"></div>
                <div class="col-md-6"><label class="form-label small">Description</label><input name="description" value="{{ $plan?->description }}" class="form-control form-control-sm"></div>
                <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                    @foreach ($features as $key => $label)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="features[]" value="{{ $key }}" id="{{ $f }}-{{ $key }}" @checked($plan?->hasFeature($key))><label class="form-check-label small" for="{{ $f }}-{{ $key }}">{{ $label }}</label></div>
                    @endforeach
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $f }}-active" @checked($plan?->is_active ?? true)><label class="form-check-label small" for="{{ $f }}-active">Available</label></div>
                    <button class="btn btn-sm btn-primary ms-auto">{{ $plan ? 'Save' : 'Create plan' }}</button>
                </div>
            </div>
        </form>
    @endforeach
@endsection

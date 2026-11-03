@extends('layouts.app')
@section('title', $mess->name)
@section('content')
    <h1 class="h4 mb-1">{{ $mess->name }}</h1>
    <p class="text-muted mb-4">
        @if ($member)
            You are <strong>{{ $member->member_code }}</strong> ·
            {{ $member->roles->pluck('name')->join(', ') ?: 'No role' }}
        @else
            Organization owner (not a member of this mess)
        @endif
    </p>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">Active members</div>
                <div class="value">{{ $activeMembers }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">All members</div>
                <div class="value">{{ $totalMembers }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">Meal tracking</div>
                <div class="fw-semibold mt-2">{{ $mess->default_meal_tracking_mode === \App\Enums\MealTrackingMode::Individual ? 'Individual' : 'Daily total' }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">Currency</div>
                <div class="fw-semibold mt-2">{{ $mess->currency }}</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Your permissions here</div>
        <div class="card-body">
            @foreach ($permissions as $permission)
                <span class="badge text-bg-light border me-1 mb-1">{{ $permission }}</span>
            @endforeach
        </div>
    </div>
@endsection

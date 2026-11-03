@extends('layouts.app')
@section('title', 'Organizations')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Your organizations</h1>
        <a href="{{ route('tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New organization</a>
    </div>

    @forelse ($tenants as $tenant)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <a class="fw-semibold text-decoration-none" href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
                @if ($tenant->owner_id === auth()->id())
                    <span class="badge text-bg-primary">Owner</span>
                @endif
            </div>
            <div class="list-group list-group-flush">
                @forelse ($messes->get($tenant->id, collect()) as $mess)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('mess.dashboard', ['tenant' => $tenant, 'mess' => $mess]) }}">
                        <span><i class="bi bi-house me-2 text-muted"></i>{{ $mess->name }}</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                @empty
                    <div class="list-group-item text-muted small">No messes you can access yet.</div>
                @endforelse
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5">
            <i class="bi bi-buildings fs-1 d-block mb-2"></i>
            You are not part of any organization yet. Create one, or ask a mess administrator to add you.
        </div>
    @endforelse
@endsection

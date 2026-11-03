@extends('layouts.app')
@section('title', $tenant->name)
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $tenant->name }}</h1>
        @can('createMess', $tenant)
            <a href="{{ route('messes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New mess</a>
        @endcan
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">Messes</div>
                <div class="value">{{ $messes->count() }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">Active members</div>
                <div class="value">{{ $messes->sum('active_members_count') }}</div>
            </div></div>
        </div>
        @if ($isOwner)
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                    <div class="text-muted small">Organization users</div>
                    <div class="value">{{ $userCount }}</div>
                </div></div>
            </div>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Messes</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Status</th><th class="text-end">Active members</th></tr></thead>
                <tbody>
                @forelse ($messes as $mess)
                    <tr>
                        <td><a href="{{ route('mess.dashboard', ['mess' => $mess]) }}">{{ $mess->name }}</a></td>
                        <td><span class="badge text-bg-{{ $mess->status->value === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($mess->status->value) }}</span></td>
                        <td class="text-end">{{ $mess->active_members_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">No messes yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

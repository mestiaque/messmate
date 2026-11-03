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

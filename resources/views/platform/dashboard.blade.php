@extends('layouts.app')
@section('title', 'Platform')
@section('content')
    <h1 class="h4 mb-3">Platform dashboard</h1>
    <div class="row g-3 mb-4">
        @foreach ($stats as $label => $value)
            <div class="col-6 col-md-4 col-xl">
                <div class="card border-0 shadow-sm stat-card"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="value">{{ number_format($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Recent organizations</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Owner</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                @forelse ($recentTenants as $tenant)
                    <tr>
                        <td><a href="{{ route('platform.tenants.show', $tenant->id) }}">{{ $tenant->name }}</a></td>
                        <td>{{ $tenant->owner->name }}</td>
                        <td>@include('platform.tenants._status')</td>
                        <td>{{ $tenant->created_at->toDateString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No organizations yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

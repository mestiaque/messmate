@extends('layouts.app')
@section('title', 'Organizations')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Organizations</h1>
        <form class="d-flex gap-2" method="GET">
            <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or slug">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach (\App\Enums\TenantStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ ucfirst($status->value) }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Owner</th><th class="text-end">Messes</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                @forelse ($tenants as $tenant)
                    <tr>
                        <td><a href="{{ route('platform.tenants.show', $tenant->id) }}">{{ $tenant->name }}</a><div class="small text-muted">{{ $tenant->slug }}</div></td>
                        <td>{{ $tenant->owner->name }}<div class="small text-muted">{{ $tenant->owner->email ?? $tenant->owner->phone }}</div></td>
                        <td class="text-end">{{ $tenant->messes_count }}</td>
                        <td>@include('platform.tenants._status')</td>
                        <td>{{ $tenant->created_at->toDateString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No organizations match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $tenants->links() }}</div>
@endsection

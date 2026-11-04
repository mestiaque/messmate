@extends('layouts.app')
@section('title', 'Support tickets')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Support tickets</h1>
        <form method="GET"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status">
            @foreach (['open', 'answered', 'closed', 'all'] as $s)<option value="{{ $s }}" @selected(request('status', 'open') === $s)>{{ ucfirst($s) }}</option>@endforeach
        </select></form>
    </div>
    <div class="card border-0 shadow-sm"><div class="list-group list-group-flush">
        @forelse ($tickets as $t)
            <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('platform.tickets.show', $t) }}">
                <span>{{ $t->subject }} <span class="small text-muted">· {{ $t->tenant->name }} · {{ $t->user?->name }}</span></span>
                <span class="small text-muted">{{ $t->updated_at->diffForHumans() }}</span></a>
        @empty
            <div class="list-group-item text-muted">No tickets.</div>
        @endforelse
    </div></div>
    <div class="mt-3">{{ $tickets->links() }}</div>
@endsection

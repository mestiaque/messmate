@extends('layouts.app')
@section('title', $ticket->subject)
@section('narrow', '1')
@section('content')
    <h1 class="h4 mb-1">{{ $ticket->subject }}</h1>
    <div class="small text-muted mb-3">{{ $tenant->name }} · {{ $ticket->status }}</div>
    <div>
        @foreach ($ticket->messages as $m)
            <div class="card border-0 shadow-sm mb-2 {{ $m->is_staff ? 'ms-4 bg-primary-subtle' : 'me-4' }}"><div class="card-body py-2">
                <div class="small text-muted">{{ $m->is_staff ? config('app.name').' support' : $m->user?->name }} · {{ $m->created_at->format('d M Y H:i') }}</div>
                <div style="white-space: pre-line">{{ $m->body }}</div>
            </div></div>
        @endforeach
        <form method="POST" action="{{ $replyRoute }}" class="card border-0 shadow-sm mt-3"><div class="card-body">@csrf
            <textarea name="body" rows="3" class="form-control mb-2" required aria-label="Reply"></textarea>
            @if ($staff ?? false)
                <select name="status" class="form-select form-select-sm d-inline-block w-auto me-2" aria-label="Status"><option value="answered">Mark answered</option><option value="closed">Close ticket</option></select>
            @endif
            <button class="btn btn-primary btn-sm">Reply</button>
        </div></form>
    </div>
@endsection

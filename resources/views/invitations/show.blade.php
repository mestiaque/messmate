@extends('layouts.app')
@section('title', 'Invitation')
@section('content')
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 520px">
        <div class="card-body p-4 text-center">
            <i class="bi bi-envelope-open fs-1 text-primary"></i>
            <h1 class="h5 mt-2">Join {{ $invitation->mess->name }}</h1>
            <p class="text-muted">{{ $invitation->mess->tenant->name }} invited you as <strong>{{ $invitation->role?->name ?? 'Member' }}</strong>.</p>
            @if ($invitation->isUsable())
                <form method="POST" action="{{ route('invitations.accept', $token) }}">
                    @csrf
                    <button class="btn btn-primary">Accept invitation</button>
                </form>
                <p class="small text-muted mt-3 mb-0">Sent to {{ $invitation->email ?? $invitation->phone }}. You must be logged in with that email or phone.</p>
            @else
                <div class="alert alert-warning mb-0">This invitation has expired or was already used.</div>
            @endif
        </div>
    </div>
@endsection

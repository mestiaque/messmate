@extends('layouts.app')
@section('title', 'Platform announcements')
@section('content')
    <h1 class="h4 mb-3">Platform announcements <span class="small text-muted fw-normal">shown to every organization</span></h1>
    <form method="POST" action="{{ route('platform.announcements.store') }}" class="card border-0 shadow-sm mb-3" style="max-width: 760px"><div class="card-body">@csrf
        <input name="title" class="form-control mb-2" placeholder="Title" required aria-label="Title">
        <textarea name="body" rows="2" class="form-control mb-2" placeholder="Message" required aria-label="Message"></textarea>
        <div class="d-flex gap-2 align-items-end"><div><label class="form-label small" for="expires_at">Show until</label><input id="expires_at" type="datetime-local" name="expires_at" class="form-control form-control-sm"></div><button class="btn btn-primary ms-auto">Publish</button></div>
    </div></form>
    <div class="card border-0 shadow-sm" style="max-width: 760px"><ul class="list-group list-group-flush">
        @forelse ($announcements as $a)
            <li class="list-group-item d-flex justify-content-between gap-2">
                <div><strong>{{ $a->title }}</strong><div class="small">{{ $a->body }}</div><div class="small text-muted">{{ $a->published_at->format('d M Y H:i') }}{{ $a->expires_at ? ' → '.$a->expires_at->format('d M Y H:i') : '' }}</div></div>
                @if (! $a->expires_at || $a->expires_at->isFuture())
                    <form method="POST" action="{{ route('platform.announcements.withdraw', $a) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">Withdraw</button></form>
                @endif
            </li>
        @empty
            <li class="list-group-item text-muted">None.</li>
        @endforelse
    </ul></div>
@endsection

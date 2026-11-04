@extends('layouts.app')
@section('title', 'Support')
@section('content')
    <h1 class="h4 mb-3">Support</h1>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">New ticket</div>
                <form method="POST" action="{{ route('support.store') }}" class="card-body">@csrf
                    <input name="subject" value="{{ old('subject') }}" class="form-control mb-2" placeholder="Subject" required aria-label="Subject">
                    <textarea name="body" rows="4" class="form-control mb-2" placeholder="Describe the problem" required aria-label="Message">{{ old('body') }}</textarea>
                    <button class="btn btn-primary">Send</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm"><div class="list-group list-group-flush">
                @forelse ($tickets as $t)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('support.show', ['supportTicket' => $t]) }}">
                        <span>{{ $t->subject }}</span><span class="badge text-bg-{{ ['open' => 'warning', 'answered' => 'success', 'closed' => 'secondary'][$t->status] }}">{{ $t->status }}</span></a>
                @empty
                    <div class="list-group-item text-muted">No tickets.</div>
                @endforelse
            </div></div>
            <div class="mt-3">{{ $tickets->links() }}</div>
        </div>
    </div>
@endsection

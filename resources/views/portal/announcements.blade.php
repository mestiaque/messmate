@extends('layouts.app')
@section('title', 'Announcements')
@section('content')
    <h1 class="h4 mb-3">Announcements & member portal</h1>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">New announcement</div>
                <form method="POST" action="{{ route('announcements.store') }}" class="card-body">
                    @csrf
                    <div class="mb-2"><input name="title" value="{{ old('title') }}" class="form-control" placeholder="Title" required aria-label="Title"></div>
                    <div class="mb-2"><textarea name="body" rows="3" class="form-control" placeholder="Message to all members" required aria-label="Message">{{ old('body') }}</textarea></div>
                    <div class="d-flex gap-2 align-items-end">
                        <div><label class="form-label small" for="expires_at">Show until (optional)</label><input id="expires_at" type="datetime-local" name="expires_at" class="form-control form-control-sm"></div>
                        <button class="btn btn-primary ms-auto">Publish</button>
                    </div>
                </form>
            </div>
            <div class="card border-0 shadow-sm">
                <ul class="list-group list-group-flush">
                    @forelse ($announcements as $a)
                        @php($live = $a->expires_at === null || $a->expires_at->isFuture())
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $a->title }}</strong>
                                @if ($live)
                                    <form method="POST" action="{{ route('announcements.destroy', ['announcement' => $a]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0">Withdraw</button></form>
                                @else
                                    <span class="badge text-bg-light border">ended</span>
                                @endif
                            </div>
                            <div class="small">{{ $a->body }}</div>
                            <div class="small text-muted">{{ $a->author?->name }} · {{ $a->published_at->format('d M Y H:i') }}{{ $a->expires_at ? ' · until '.$a->expires_at->format('d M H:i') : '' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No announcements.</li>
                    @endforelse
                </ul>
            </div>
            <div class="mt-3">{{ $announcements->links() }}</div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Member self-service</div>
                <form method="POST" action="{{ route('portal.settings') }}" class="card-body">
                    @csrf @method('PUT')
                    <input type="hidden" name="self_service_meals" value="0">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="self_service_meals" value="1" id="ssm" @checked($settings['self_service_meals'])>
                        <label class="form-check-label" for="ssm">Members can enter their own meals</label></div>
                    <input type="hidden" name="require_approval" value="0">
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="require_approval" value="1" id="ra" @checked($settings['require_approval'])>
                        <label class="form-check-label" for="ra">Their entries need approval</label></div>
                    <p class="small text-muted">Members can never change an entry after it is approved.</p>
                    <button class="btn btn-outline-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
@endsection

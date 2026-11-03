@extends('layouts.app')
@section('title', 'Members')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Members</h1>
        <form class="d-flex gap-2" method="GET">
            <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, code, phone">
            <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
    </div>

    @if ($canManage)
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('members.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-plus"></i> Add member without account</a>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#invite"><i class="bi bi-envelope"></i> Invite by email / phone</button>
        </div>
        <div class="collapse @if($errors->hasAny(['email','phone','role_id'])) show @endif" id="invite">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Invite</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('members.invite') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4"><label class="form-label small" for="inv_email">Email</label><input id="inv_email" type="email" name="email" value="{{ old('email') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label small" for="inv_phone">or phone</label><input id="inv_phone" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="+8801XXXXXXXXX"></div>
                        <div class="col-md-3">
                            <label class="form-label small" for="inv_role">Role</label>
                            <select id="inv_role" name="role_id" class="form-select">
                                @foreach ($roles as $role)
                                    @if ($canManageRoles || $role->slug === \App\Models\Role::MEMBER)
                                        <option value="{{ $role->id }}" @selected($role->slug === \App\Models\Role::MEMBER)>{{ $role->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><button class="btn btn-primary w-100">Create link</button></div>
                    </form>
                    @if ($invitations->isNotEmpty())
                        <hr>
                        <div class="small fw-semibold mb-2">Pending invitations</div>
                        <ul class="list-group list-group-flush small">
                            @foreach ($invitations as $invitation)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span>{{ $invitation->email ?? $invitation->phone }} · {{ $invitation->role?->name }} · expires {{ $invitation->expires_at->diffForHumans() }}</span>
                                    <form method="POST" action="{{ route('members.invitations.revoke', ['invitation' => $invitation]) }}">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger">Revoke</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Add a registered user</div>
            <div class="card-body">
                <form method="POST" action="{{ route('members.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small" for="login">Email or phone</label>
                        <input id="login" name="login" value="{{ old('login') }}" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="room_number">Room</label>
                        <input id="room_number" name="room_number" value="{{ old('room_number') }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="joined_at">Joined</label>
                        <input id="joined_at" type="date" name="joined_at" value="{{ old('joined_at', now($mess->timezone)->toDateString()) }}" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="role">Role</label>
                        <select id="role" name="roles[]" class="form-select">
                            @foreach ($roles as $role)
                                @if ($canManageRoles || $role->slug === \App\Models\Role::MEMBER)
                                    <option value="{{ $role->id }}" @selected($role->slug === \App\Models\Role::MEMBER)>{{ $role->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Add</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr><th>Code</th><th>Name</th><th class="d-none d-md-table-cell">Phone</th><th class="d-none d-md-table-cell">Room</th><th>Status</th><th>Roles</th></tr>
                </thead>
                <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td class="text-muted">{{ $member->member_code }}</td>
                        <td><a href="{{ route('members.show', ['member' => $member]) }}">{{ $member->full_name }}</a>@unless($member->user_id)<span class="badge text-bg-light border ms-1">no account</span>@endunless</td>
                        <td class="d-none d-md-table-cell">{{ $member->phone }}</td>
                        <td class="d-none d-md-table-cell">{{ $member->room_number }}</td>
                        <td><span class="badge text-bg-{{ $member->isActive() ? 'success' : 'secondary' }}" @if($member->left_at) title="Left {{ $member->left_at->toDateString() }}" @endif>{{ ucfirst($member->membership_status->value) }}</span></td>
                        <td>
                            @if ($canManageRoles)
                                <form method="POST" action="{{ route('members.roles.sync', ['member' => $member]) }}" class="d-flex gap-1 flex-wrap">
                                    @csrf @method('PUT')
                                    @foreach ($roles as $role)
                                        <div class="form-check form-check-inline small me-2">
                                            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="r{{ $member->id }}-{{ $role->id }}" @checked($member->roles->contains($role->id))>
                                            <label class="form-check-label" for="r{{ $member->id }}-{{ $role->id }}">{{ $role->name }}</label>
                                        </div>
                                    @endforeach
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            @else
                                {{ $member->roles->pluck('name')->join(', ') }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No members found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $members->links() }}</div>
@endsection

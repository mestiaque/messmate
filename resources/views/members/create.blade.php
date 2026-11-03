@extends('layouts.app')
@section('title', 'Add member')
@section('content')
    <h1 class="h4 mb-1">Add member without account</h1>
    <p class="text-muted small">For people who will not log in. You can link a registered account later.</p>
    <div class="card border-0 shadow-sm" style="max-width: 720px">
        <div class="card-body">
            <form method="POST" action="{{ route('members.store-manual') }}">
                @csrf
                @include('members._fields')
                <div class="mb-3">
                    <label class="form-label" for="role">Role</label>
                    <select id="role" name="roles[]" class="form-select">
                        @foreach ($roles as $role)
                            @if ($canManageRoles || $role->slug === \App\Models\Role::MEMBER)
                                <option value="{{ $role->id }}" @selected($role->slug === \App\Models\Role::MEMBER)>{{ $role->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary">Add member</button>
                <a href="{{ route('members.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection

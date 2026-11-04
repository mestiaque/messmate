@extends('layouts.app')
@section('title', 'Profile')
@section('narrow', '1')
@section('content')
    <h1 class="h4 mb-3">Profile</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control">
                    </div>
                </div>
                <hr>
                <p class="text-muted small">Leave blank to keep your current password.</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="current_password">Current password</label>
                        <input id="current_password" type="password" name="current_password" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="password">New password</label>
                        <input id="password" type="password" name="password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="password_confirmation">Confirm</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <button class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
    <h1 class="h3 mb-1">Reset your password</h1>
    <p class="text-muted mb-4">We will email you a reset link.</p>
    @if (session('status'))<div class="alert alert-success small">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary w-100">Email reset link</button>
    </form>
    <p class="text-center small mt-3 mb-0"><a href="{{ route('login') }}">Back to log in</a></p>
@endsection

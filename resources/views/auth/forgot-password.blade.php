@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
    <h2 class="h5 mb-3">Reset your password</h2>
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

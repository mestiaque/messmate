@extends('layouts.guest')
@section('title', 'Log in')
@section('content')
    <h2 class="h5 mb-3">Log in</h2>
    @if (session('status'))<div class="alert alert-success small">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="login">Email or phone</label>
            <input id="login" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror" required autofocus autocomplete="username">
            @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <button class="btn btn-primary w-100">Log in</button>
    </form>
    <p class="text-center small mt-3 mb-0"><a href="{{ route('password.request') }}">Forgot password?</a> · No account? <a href="{{ route('register') }}">Register</a></p>
@endsection

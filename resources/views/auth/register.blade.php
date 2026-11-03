@extends('layouts.guest')
@section('title', 'Register')
@section('content')
    <h2 class="h5 mb-3">Create account</h2>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="phone">Phone <span class="text-muted small">(email or phone required)</span></label>
            <input id="phone" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="+8801XXXXXXXXX" autocomplete="tel">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-100">Register</button>
    </form>
    <p class="text-center small mt-3 mb-0">Already registered? <a href="{{ route('login') }}">Log in</a></p>
@endsection

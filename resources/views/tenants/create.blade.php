@extends('layouts.app')
@section('title', 'New organization')
@section('content')
    <h1 class="h4 mb-3">New organization</h1>
    <div class="card border-0 shadow-sm" style="max-width: 720px">
        <div class="card-body">
            <form method="POST" action="{{ route('tenants.store') }}">
                @csrf
                @include('tenants._form')
                <button class="btn btn-primary">Create organization</button>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.app')
@section('title', 'Organization settings')
@section('content')
    <h1 class="h4 mb-3">Organization settings</h1>
    <div class="card border-0 shadow-sm" style="max-width: 720px">
        <div class="card-body">
            <form method="POST" action="{{ route('tenant.settings.update') }}">
                @csrf @method('PUT')
                @include('tenants._form')
                <button class="btn btn-primary">Save changes</button>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.app')
@section('title', 'New mess')
@section('narrow', '1')
@section('content')
    <h1 class="h4 mb-3">New mess in {{ $tenant->name }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('messes.store') }}">
                @csrf
                @include('messes._form')
                <div class="form-check mb-3">
                    <input type="hidden" name="join_as_member" value="0">
                    <input class="form-check-input" type="checkbox" name="join_as_member" id="join_as_member" value="1" @checked(old('join_as_member', true))>
                    <label class="form-check-label" for="join_as_member">Add me to this mess as Mess Administrator</label>
                </div>
                <button class="btn btn-primary">Create mess</button>
            </form>
        </div>
    </div>
@endsection

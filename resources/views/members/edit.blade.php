@extends('layouts.app')
@section('title', 'Edit member')
@section('narrow', '1')
@section('content')
    <h1 class="h4 mb-3">Edit {{ $member->full_name }} <span class="text-muted">{{ $member->member_code }}</span></h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('members.update', ['member' => $member]) }}">
                @csrf @method('PUT')
                @include('members._fields')
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('members.show', ['member' => $member]) }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.app')
@section('title', 'Mess settings')
@section('content')
    @php($tenant = $mess->tenant)
    <h1 class="h4 mb-3">Mess settings</h1>
    <div class="card border-0 shadow-sm" style="max-width: 720px">
        <div class="card-body">
            <form method="POST" action="{{ route('mess.update') }}">
                @csrf @method('PUT')
                @include('messes._form')
                <div class="mb-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        @foreach (\App\Enums\MessStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $mess->status->value) === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary">Save changes</button>
            </form>
        </div>
    </div>
@endsection

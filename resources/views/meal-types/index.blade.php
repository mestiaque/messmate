@extends('layouts.app')
@section('title', 'Meal types')
@section('content')
    <h1 class="h4 mb-1">Meal types</h1>
    <p class="text-muted small">The unit factor weights each meal (e.g. breakfast 0.5). Changing a factor applies to new entries only; recorded entries keep the factor they were saved with.</p>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Code</th><th>Factor</th><th>Order</th><th>Default on</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @foreach ($types as $type)
                    @php($f = 'mt-'.$type->id)
                    <tr>
                            <td><input form="{{ $f }}" name="name" value="{{ $type->name }}" class="form-control form-control-sm" required style="min-width: 120px"></td>
                            <td><input form="{{ $f }}" name="code" value="{{ $type->code }}" class="form-control form-control-sm" required style="min-width: 90px"></td>
                            <td><input form="{{ $f }}" name="unit_factor" value="{{ rtrim(rtrim($type->unit_factor, '0'), '.') }}" class="form-control form-control-sm" inputmode="decimal" required style="width: 80px"></td>
                            <td><input form="{{ $f }}" name="sort_order" type="number" value="{{ $type->sort_order }}" class="form-control form-control-sm" style="width: 70px"></td>
                            <td><input form="{{ $f }}" type="hidden" name="default_enabled" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="default_enabled" value="1" @checked($type->default_enabled)></td>
                            <td><input form="{{ $f }}" type="hidden" name="is_active" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($type->is_active)></td>
                            <td>
                                <form method="POST" action="{{ route('meal-types.update', ['mealType' => $type]) }}" id="{{ $f }}">@csrf @method('PUT')</form>
                                <button class="btn btn-sm btn-outline-primary" form="{{ $f }}">Save</button>
                            </td>
                    </tr>
                @endforeach
                @php($f = 'mt-new')
                <tr class="table-light">
                        <td><input form="{{ $f }}" name="name" value="{{ old('name') }}" class="form-control form-control-sm" placeholder="New meal type" required></td>
                        <td><input form="{{ $f }}" name="code" value="{{ old('code') }}" class="form-control form-control-sm" placeholder="code" required></td>
                        <td><input form="{{ $f }}" name="unit_factor" value="{{ old('unit_factor', '1') }}" class="form-control form-control-sm" inputmode="decimal" required></td>
                        <td><input form="{{ $f }}" name="sort_order" type="number" value="{{ old('sort_order', $types->max('sort_order') + 1) }}" class="form-control form-control-sm"></td>
                        <td><input form="{{ $f }}" type="hidden" name="default_enabled" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="default_enabled" value="1" checked></td>
                        <td><input form="{{ $f }}" type="hidden" name="is_active" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="is_active" value="1" checked></td>
                        <td>
                            <form method="POST" action="{{ route('meal-types.store') }}" id="{{ $f }}">@csrf</form>
                            <button class="btn btn-sm btn-primary" form="{{ $f }}">Add</button>
                        </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

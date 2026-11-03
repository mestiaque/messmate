@extends('layouts.app')
@section('title', 'Categories')
@section('content')
    <h1 class="h4 mb-3">Bazar & expense categories</h1>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Bazar categories <span class="small text-muted fw-normal">— food-related ones feed the meal rate</span></div>
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Food</th><th>Active</th><th></th></tr></thead>
                    @foreach ($bazarCategories as $c)
                        @php($f = 'bc-'.$c->id)
                        <tr>
                            <td><input form="{{ $f }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required aria-label="Name"></td>
                            <td><input form="{{ $f }}" type="hidden" name="is_food_related" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="is_food_related" value="1" @checked($c->is_food_related) aria-label="Food related"></td>
                            <td><input form="{{ $f }}" type="hidden" name="is_active" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($c->is_active) aria-label="Active"></td>
                            <td><form id="{{ $f }}" method="POST" action="{{ route('finance.bazar-categories.update', ['bazarCategory' => $c]) }}">@csrf @method('PUT')</form><button form="{{ $f }}" class="btn btn-sm btn-outline-primary">Save</button></td>
                        </tr>
                    @endforeach
                    <tr class="table-light">
                        <td><input form="bc-new" name="name" class="form-control form-control-sm" placeholder="New category" required aria-label="New bazar category"></td>
                        <td><input form="bc-new" type="hidden" name="is_food_related" value="0"><input form="bc-new" type="checkbox" class="form-check-input" name="is_food_related" value="1" checked aria-label="Food related"></td>
                        <td></td>
                        <td><form id="bc-new" method="POST" action="{{ route('finance.bazar-categories.store') }}">@csrf</form><button form="bc-new" class="btn btn-sm btn-primary">Add</button></td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Expense categories</div>
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Type</th><th>Active</th><th></th></tr></thead>
                    @foreach ($expenseCategories as $c)
                        @php($f = 'ec-'.$c->id)
                        <tr>
                            <td><input form="{{ $f }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required aria-label="Name"></td>
                            <td><select form="{{ $f }}" name="category_type" class="form-select form-select-sm" aria-label="Type">
                                @foreach (\App\Enums\ExpenseCategoryType::cases() as $t)<option value="{{ $t->value }}" @selected($c->category_type === $t)>{{ ucfirst($t->value) }}</option>@endforeach
                            </select></td>
                            <td><input form="{{ $f }}" type="hidden" name="is_active" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($c->is_active) aria-label="Active"></td>
                            <td><form id="{{ $f }}" method="POST" action="{{ route('finance.expense-categories.update', ['expenseCategory' => $c]) }}">@csrf @method('PUT')</form><button form="{{ $f }}" class="btn btn-sm btn-outline-primary">Save</button></td>
                        </tr>
                    @endforeach
                    <tr class="table-light">
                        <td><input form="ec-new" name="name" class="form-control form-control-sm" placeholder="New category" required aria-label="New expense category"></td>
                        <td><select form="ec-new" name="category_type" class="form-select form-select-sm" aria-label="Type">
                            @foreach (\App\Enums\ExpenseCategoryType::cases() as $t)<option value="{{ $t->value }}">{{ ucfirst($t->value) }}</option>@endforeach
                        </select></td>
                        <td></td>
                        <td><form id="ec-new" method="POST" action="{{ route('finance.expense-categories.store') }}">@csrf</form><button form="ec-new" class="btn btn-sm btn-primary">Add</button></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
@endsection

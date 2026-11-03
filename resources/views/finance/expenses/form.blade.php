@extends('layouts.app')
@section('title', $expense->exists ? 'Edit expense' : 'New expense')
@section('content')
    @php($method = old('allocation_method', $expense->allocation_method?->value))
    @php($shares = old('shares', $expense->exists ? $expense->allocations->mapWithKeys(fn ($a) => [$a->member_id => $method === 'usage_based' ? rtrim(rtrim($a->allocation_basis, '0'), '.') : $a->allocated_amount])->all() : []))
    <h1 class="h4 mb-3">{{ $expense->exists ? 'Edit expense' : 'New expense' }}</h1>
    <form method="POST" enctype="multipart/form-data" action="{{ $expense->exists ? route('expenses.update', ['expense' => $expense]) : route('expenses.store') }}" id="expense-form">
        @csrf
        @if ($expense->exists) @method('PUT') @endif
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label small" for="title">Title</label>
                <input id="title" name="title" value="{{ old('title', $expense->title) }}" class="form-control" required placeholder="e.g. November electricity bill"></div>
            <div class="col-md-3"><label class="form-label small" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="form-select">
                    @foreach ($categories as $c)<option value="{{ $c->id }}" data-type="{{ $c->category_type->value }}" @selected(old('category_id', $expense->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
                </select></div>
            <div class="col-6 col-md-3"><label class="form-label small" for="expense_date">Date</label>
                <input id="expense_date" type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date?->toDateString()) }}" class="form-control" required></div>
            <div class="col-6 col-md-3"><label class="form-label small" for="amount">Amount ({{ $mess->currency }})</label>
                <input id="amount" name="amount" value="{{ old('amount', $expense->amount) }}" class="form-control" inputmode="decimal" required></div>
            <div class="col-md-3"><label class="form-label small" for="payment_method">Paid with</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    @foreach (\App\Enums\PaymentMethod::cases() as $pm)<option value="{{ $pm->value }}" @selected(old('payment_method', $expense->payment_method?->value) === $pm->value)>{{ $pm->label() }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label small" for="paid_by_member_id">Paid by member</label>
                <select id="paid_by_member_id" name="paid_by_member_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($members as $m)<option value="{{ $m->id }}" @selected(old('paid_by_member_id', $expense->paid_by_member_id) == $m->id)>{{ $m->full_name }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label small" for="allocation_method">Split between members</label>
                <select id="allocation_method" name="allocation_method" class="form-select">
                    @foreach (['equal' => 'Equally', 'meal_based' => 'By meals eaten', 'custom' => 'Custom amounts', 'usage_based' => 'By usage', 'excluded' => 'Not charged to members'] as $v => $l)
                        <option value="{{ $v }}" @selected($method === $v)>{{ $l }}</option>
                    @endforeach
                </select>
                <div class="form-text">Equal and meal-based shares are calculated at settlement.</div></div>
            <div class="col-md-6"><label class="form-label small" for="description">Notes</label>
                <input id="description" name="description" value="{{ old('description', $expense->description) }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label small" for="receipt">Receipt</label>
                <input id="receipt" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control">
                @if ($expense->receipt_path)<div class="small mt-1"><a href="{{ route('receipts.show', ['type' => 'expense', 'id' => $expense->id]) }}" target="_blank">Current receipt</a></div>@endif</div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3 js-shares">
            <div class="card-header bg-white fw-semibold"><span class="js-label-custom">Amount per member (must add up to the total)</span><span class="js-label-usage">Usage per member (e.g. units, days) — amounts are split proportionally</span></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                    @foreach ($members as $m)
                        <tr><td>{{ $m->member_code }} · {{ $m->full_name }}</td>
                            <td style="width: 10rem"><input name="shares[{{ $m->id }}]" value="{{ $shares[$m->id] ?? '' }}" class="form-control form-control-sm js-share" inputmode="decimal" aria-label="Share for {{ $m->full_name }}"></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small">Entered total: <strong id="share-sum">0</strong></div>
        </div>
        <button class="btn btn-primary">{{ $expense->exists ? 'Save & resubmit' : 'Save expense' }}</button>
    </form>
    <script>
        (() => {
            const method = document.getElementById('allocation_method');
            const category = document.getElementById('category_id');
            const sync = () => {
                const v = method.value, shares = document.querySelector('.js-shares');
                shares.classList.toggle('d-none', ! ['custom', 'usage_based'].includes(v));
                shares.querySelectorAll('input').forEach(i => i.disabled = ! ['custom', 'usage_based'].includes(v));
                document.querySelector('.js-label-custom').classList.toggle('d-none', v !== 'custom');
                document.querySelector('.js-label-usage').classList.toggle('d-none', v !== 'usage_based');
            };
            // Food expenses are always shared through the meal rate.
            const syncCategory = () => {
                if (category.selectedOptions[0]?.dataset.type === 'food') method.value = 'meal_based';
                sync();
            };
            const sum = () => document.getElementById('share-sum').textContent =
                [...document.querySelectorAll('.js-share')].reduce((t, i) => t + (parseFloat(i.value) || 0), 0).toFixed(2);
            method.addEventListener('change', sync);
            category.addEventListener('change', syncCategory);
            document.querySelectorAll('.js-share').forEach(i => i.addEventListener('input', sum));
            sync(); sum();
        })();
    </script>
@endsection

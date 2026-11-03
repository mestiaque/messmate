@extends('layouts.app')
@section('title', $entry->exists ? 'Edit purchase' : 'New purchase')
@section('content')
    @php($mode = old('entry_mode', $entry->entry_mode))
    @php($items = old('items', $entry->exists ? $entry->items->map(fn ($i) => ['item_name' => $i->item_name, 'category_id' => $i->category_id, 'quantity' => rtrim(rtrim($i->quantity, '0'), '.'), 'unit' => $i->unit, 'unit_price' => rtrim(rtrim($i->unit_price, '0'), '.'), 'discount' => $i->discount])->all() : []))
    @php($items = array_pad($items, max(count($items) + 2, 4), []))
    <h1 class="h4 mb-3">{{ $entry->exists ? 'Edit purchase #'.$entry->id : 'New bazar purchase' }}</h1>
    <form method="POST" enctype="multipart/form-data" action="{{ $entry->exists ? route('bazar.update', ['bazarEntry' => $entry]) : route('bazar.store') }}" id="bazar-form">
        @csrf
        @if ($entry->exists) @method('PUT') @endif
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body row g-3">
            <div class="col-6 col-md-3"><label class="form-label small" for="purchase_date">Date</label>
                <input id="purchase_date" type="date" name="purchase_date" value="{{ old('purchase_date', $entry->purchase_date?->toDateString()) }}" class="form-control" required></div>
            <div class="col-6 col-md-3"><label class="form-label small" for="purchased_by">Purchased by</label>
                <select id="purchased_by" name="purchased_by" class="form-select" required>
                    @foreach ($members as $m)<option value="{{ $m->id }}" @selected(old('purchased_by', $entry->purchased_by) == $m->id)>{{ $m->full_name }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label small" for="payment_method">Paid with</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    @foreach (\App\Enums\PaymentMethod::cases() as $pm)<option value="{{ $pm->value }}" @selected(old('payment_method', $entry->payment_method?->value) === $pm->value)>{{ $pm->label() }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label small">Entry</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="entry_mode" id="mode-itemized" value="itemized" @checked($mode === 'itemized')><label class="btn btn-outline-primary" for="mode-itemized">Itemized</label>
                    <input type="radio" class="btn-check" name="entry_mode" id="mode-total" value="total" @checked($mode === 'total')><label class="btn btn-outline-primary" for="mode-total">Total only</label>
                </div></div>
            <div class="col-md-6"><label class="form-label small" for="description">Description</label>
                <input id="description" name="description" value="{{ old('description', $entry->description) }}" class="form-control" placeholder="e.g. Karwan Bazar weekly"></div>
            <div class="col-md-6"><label class="form-label small" for="receipt">Receipt (jpg, png, pdf · max 5 MB)</label>
                <input id="receipt" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control">
                @if ($entry->receipt_path)<div class="small mt-1"><a href="{{ route('receipts.show', ['type' => 'bazar', 'id' => $entry->id]) }}" target="_blank">Current receipt</a> (upload to replace)</div>@endif</div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3 js-total">
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label small" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $entry->category_id) == $c->id)>{{ $c->name }}{{ $c->is_food_related ? '' : ' (non-food)' }}</option>@endforeach
                    </select></div>
                <div class="col-md-4"><label class="form-label small" for="amount">Amount</label>
                    <input id="amount" name="amount" value="{{ old('amount', $entry->exists && $entry->entry_mode === 'total' ? $entry->subtotal : '') }}" class="form-control js-calc" inputmode="decimal"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3 js-itemized">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="items">
                    <thead class="table-light"><tr><th>Item</th><th>Category</th><th style="width: 7rem">Qty</th><th style="width: 6rem">Unit</th><th style="width: 8rem">Unit price</th><th style="width: 7rem">Discount</th><th class="text-end" style="width: 7rem">Line</th></tr></thead>
                    <tbody>
                    @foreach ($items as $i => $item)
                        <tr>
                            <td><input name="items[{{ $i }}][item_name]" value="{{ $item['item_name'] ?? '' }}" class="form-control form-control-sm" style="min-width: 130px" aria-label="Item"></td>
                            <td><select name="items[{{ $i }}][category_id]" class="form-select form-select-sm" style="min-width: 120px" aria-label="Category">
                                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($item['category_id'] ?? null) == $c->id)>{{ $c->name }}</option>@endforeach
                            </select></td>
                            <td><input name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? '' }}" class="form-control form-control-sm js-calc js-qty" inputmode="decimal" aria-label="Quantity"></td>
                            <td><input name="items[{{ $i }}][unit]" value="{{ $item['unit'] ?? 'kg' }}" class="form-control form-control-sm" aria-label="Unit"></td>
                            <td><input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" class="form-control form-control-sm js-calc js-price" inputmode="decimal" aria-label="Unit price"></td>
                            <td><input name="items[{{ $i }}][discount]" value="{{ $item['discount'] ?? '' }}" class="form-control form-control-sm js-calc js-disc" inputmode="decimal" aria-label="Discount"></td>
                            <td class="text-end js-line">—</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white"><button type="button" class="btn btn-sm btn-outline-secondary" id="add-row"><i class="bi bi-plus"></i> Add row</button></div>
        </div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div><label class="form-label small" for="discount">Overall discount</label>
                <input id="discount" name="discount" value="{{ old('discount', $entry->exists ? $entry->discount : '') }}" class="form-control js-calc" inputmode="decimal" style="width: 9rem"></div>
            <div class="ms-auto text-end">
                <div class="small text-muted">Estimated total (the server recalculates)</div>
                <div class="fs-4 fw-semibold" id="grand">0.00</div>
            </div>
            <button class="btn btn-primary">{{ $entry->exists ? 'Save & resubmit' : 'Save purchase' }}</button>
        </div></div>
    </form>
    <script>
        (() => {
            const form = document.getElementById('bazar-form');
            const tbody = document.querySelector('#items tbody');
            const num = v => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
            const mode = () => form.querySelector('input[name=entry_mode]:checked').value;
            const recalc = () => {
                let subtotal = 0;
                if (mode() === 'itemized') {
                    tbody.querySelectorAll('tr').forEach(tr => {
                        const q = tr.querySelector('.js-qty').value, p = tr.querySelector('.js-price').value;
                        const line = num(q) * num(p) - num(tr.querySelector('.js-disc').value);
                        tr.querySelector('.js-line').textContent = (q && p) ? line.toFixed(2) : '—';
                        if (q && p) subtotal += line;
                    });
                } else {
                    subtotal = num(document.getElementById('amount').value);
                }
                document.getElementById('grand').textContent = (subtotal - num(document.getElementById('discount').value)).toFixed(2);
            };
            const toggle = () => {
                const itemized = mode() === 'itemized';
                form.querySelectorAll('.js-itemized').forEach(el => { el.classList.toggle('d-none', ! itemized); el.querySelectorAll('input,select').forEach(i => i.disabled = ! itemized); });
                form.querySelectorAll('.js-total').forEach(el => { el.classList.toggle('d-none', itemized); el.querySelectorAll('input,select').forEach(i => i.disabled = itemized); });
                recalc();
            };
            document.getElementById('add-row').addEventListener('click', () => {
                const rows = tbody.querySelectorAll('tr');
                const clone = rows[rows.length - 1].cloneNode(true);
                const index = rows.length;
                clone.querySelectorAll('input,select').forEach(el => {
                    el.name = el.name.replace(/items\[\d+\]/, `items[${index}]`);
                    if (el.tagName === 'INPUT') el.value = el.name.endsWith('[unit]') ? 'kg' : '';
                });
                clone.querySelector('.js-line').textContent = '—';
                tbody.appendChild(clone);
            });
            form.addEventListener('input', e => { if (e.target.classList.contains('js-calc')) recalc(); });
            form.querySelectorAll('input[name=entry_mode]').forEach(r => r.addEventListener('change', toggle));
            toggle();
        })();
    </script>
@endsection

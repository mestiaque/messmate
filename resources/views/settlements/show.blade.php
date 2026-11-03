@extends('layouts.app')
@section('title', 'Statement')
@section('content')
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($s = $settlement)
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $s->member->full_name }} — {{ $s->period->name }}</h1>
            <div class="small text-muted">{{ $s->member->member_code }} · version {{ $s->snapshot->calculation_version }} · <span class="badge text-bg-light border">{{ str_replace('_', ' ', $s->status) }}</span></div>
        </div>
        <button class="btn btn-sm btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
    @if ($s->status === 'superseded')<div class="alert alert-warning">This statement was superseded after the period was reopened.</div>@endif

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
            <div class="text-muted small">Closing balance</div>
            <div class="value {{ $s->closing_credit < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($s->closing_credit) }}</div>
            <div class="small">{{ $s->closing_credit < 0 ? 'Member owes the mess' : ($s->closing_credit > 0 ? 'Mess owes the member' : 'Settled') }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
            <div class="text-muted small">Paid so far</div><div class="value">{{ $fmt($s->paid_amount) }}</div>
            <div class="small">Outstanding {{ $fmt($s->outstanding()) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
            <div class="text-muted small">Meals</div><div class="value">{{ rtrim(rtrim($s->meal_units, '0'), '.') ?: '0' }} units</div>
            <div class="small">Meal cost {{ $fmt($s->meal_cost) }}</div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
            <tr><td>Opening balance</td><td></td><td></td><td class="text-end">{{ $fmt($s->opening_credit) }}</td></tr>
            @foreach ($s->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-end">{{ $item->quantity !== null ? rtrim(rtrim($item->quantity, '0'), '.') : '' }}</td>
                    <td class="text-end">{{ $item->unit_rate !== null ? number_format((float) $item->unit_rate, 4) : '' }}</td>
                    <td class="text-end {{ $item->amount < 0 ? 'text-danger' : '' }}">{{ $fmt($item->amount) }}</td>
                </tr>
            @endforeach
            <tfoot class="table-light"><tr class="fw-semibold"><td colspan="3">Closing balance</td><td class="text-end">{{ $fmt($s->closing_credit) }}</td></tr></tfoot>
        </table>
    </div>

    @if ($s->payments->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Payments</div>
            <table class="table table-sm mb-0">
                @foreach ($s->payments as $p)
                    <tr><td>{{ $p->paid_at->format('d M Y') }}</td><td>{{ $p->payment_type === 'refund' ? 'Refund to member' : 'Collected from member' }} · {{ $p->payment_method->label() }} {{ $p->transaction_reference }}</td>
                        <td class="text-end">{{ $fmt($p->amount) }}</td><td class="small text-muted">{{ $p->receiver?->name }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($canPay && $s->isPayable())
        <div class="card border-0 shadow-sm d-print-none">
            <div class="card-header bg-white fw-semibold">{{ $s->closing_credit < 0 ? 'Record collection from member' : 'Record refund to member' }}</div>
            <form method="POST" action="{{ route('settlements.payments.store', ['settlement' => $s]) }}" class="card-body row g-2 align-items-end">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <div class="col-md-3"><label class="form-label small" for="amount">Amount</label><input id="amount" name="amount" value="{{ old('amount', $s->outstanding()) }}" class="form-control" inputmode="decimal" required></div>
                <div class="col-md-3"><label class="form-label small" for="payment_method">Method</label>
                    <select id="payment_method" name="payment_method" class="form-select">@foreach (\App\Enums\PaymentMethod::forDeposits() as $pm)<option value="{{ $pm->value }}">{{ $pm->label() }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label small" for="transaction_reference">Reference</label><input id="transaction_reference" name="transaction_reference" class="form-control"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Record</button></div>
            </form>
        </div>
    @endif
@endsection

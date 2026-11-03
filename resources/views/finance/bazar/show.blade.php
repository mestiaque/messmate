@extends('layouts.app')
@section('title', 'Purchase #'.$entry->id)
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Purchase #{{ $entry->id }} @include('finance._status', ['status' => $entry->status])</h1>
            <div class="small text-muted">{{ $entry->purchase_date->format('D, d M Y') }} · {{ $entry->purchaser->full_name }} · {{ $entry->payment_method->label() }} · {{ $entry->period->name }}</div>
            @if ($entry->rejection_reason)<div class="small text-danger mt-1">Reason: {{ $entry->rejection_reason }}</div>@endif
        </div>
        <div class="d-flex flex-wrap gap-1">
            @if ($canManage && $entry->isEditable() && $entry->period->status->acceptsEntries())
                <a href="{{ route('bazar.edit', ['bazarEntry' => $entry]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
            @endif
            @if ($canApprove && $entry->status->value === 'submitted' && $entry->period->status->acceptsApprovals())
                @include('finance._decide', ['action' => route('bazar.decide', ['bazarEntry' => $entry]), 'value' => 'approve', 'label' => 'Approve', 'class' => 'btn-success'])
                @include('finance._decide', ['action' => route('bazar.decide', ['bazarEntry' => $entry]), 'value' => 'reject', 'label' => 'Reject', 'class' => 'btn-outline-danger', 'prompt' => 'Reason for rejecting?'])
            @endif
            @if ($canManage && $entry->status->value !== 'voided' && $entry->period->status->acceptsEntries())
                @include('finance._decide', ['action' => route('bazar.decide', ['bazarEntry' => $entry]), 'value' => 'void', 'label' => 'Void', 'class' => 'btn-outline-dark', 'prompt' => 'Reason for voiding? An approved purchase will be reversed in the ledger.'])
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach (['Subtotal' => $entry->subtotal, 'Discount' => $entry->discount, 'Total' => $entry->total_amount, 'Food share' => $entry->food_amount] as $label => $value)
            <div class="col-6 col-md-3"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
                <div class="text-muted small">{{ $label }}</div><div class="value">{{ number_format($value, 2) }}</div>
            </div></div></div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        @if ($entry->entry_mode === 'itemized')
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Item</th><th>Category</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Discount</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @foreach ($entry->items as $item)
                        <tr><td>{{ $item->item_name }}</td><td class="small">{{ $item->category->name }}@unless($item->category->is_food_related) <span class="badge text-bg-light border">non-food</span>@endunless</td>
                            <td class="text-end">{{ rtrim(rtrim($item->quantity, '0'), '.') }} {{ $item->unit }}</td><td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-end">{{ $item->discount > 0 ? number_format($item->discount, 2) : '' }}</td><td class="text-end fw-semibold">{{ number_format($item->total, 2) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body">Total-only entry · category <strong>{{ $entry->category?->name }}</strong>@if ($entry->description) · {{ $entry->description }}@endif</div>
        @endif
        <div class="card-footer bg-white small text-muted">
            Recorded by {{ $entry->creator?->name }} · {{ $entry->created_at->diffForHumans() }}
            @if ($entry->receipt_path) · <a href="{{ route('receipts.show', ['type' => 'bazar', 'id' => $entry->id]) }}" target="_blank">View receipt</a>@endif
        </div>
    </div>
    @include('finance._ledger_tx', ['tx' => $entry->ledgerTransaction])
@endsection

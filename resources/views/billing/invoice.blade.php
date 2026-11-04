@extends('layouts.app')
@section('title', 'Invoice '.$invoice->invoice_number)
@section('narrow', '1')
@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-0">Invoice {{ $invoice->invoice_number }}</h1>
            <div class="small text-muted">{{ config('app.name') }} · issued {{ $invoice->issued_at->format('d M Y') }} · due {{ $invoice->due_at->format('d M Y') }}</div>
        </div>
        <div class="d-flex gap-2 d-print-none"><span class="badge fs-6 text-bg-{{ $invoice->status === 'paid' ? 'success' : 'warning' }}">{{ $invoice->status }}</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button></div>
    </div>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <p class="mb-3"><strong>Billed to:</strong> {{ $tenant->name }}<br><span class="small text-muted">{{ $tenant->email }} {{ $tenant->phone }}</span></p>
        <table class="table">
            <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
            <tr><td>{{ $invoice->description }}</td><td class="text-end">{{ number_format($invoice->subtotal, 2) }}</td></tr>
            @if ($invoice->discount > 0)<tr><td>Discount</td><td class="text-end">−{{ number_format($invoice->discount, 2) }}</td></tr>@endif
            @if ($invoice->tax > 0)<tr><td>Tax</td><td class="text-end">{{ number_format($invoice->tax, 2) }}</td></tr>@endif
            <tfoot><tr class="fw-semibold"><td>Total</td><td class="text-end">{{ $invoice->currency }} {{ number_format($invoice->total, 2) }}</td></tr></tfoot>
        </table>
        @foreach ($invoice->payments as $p)
            <div class="small">Payment {{ $p->provider_reference }} · {{ $p->payment_method }} {{ $p->transaction_reference }} · <strong>{{ $p->status }}</strong>{{ $p->paid_at ? ' · '.$p->paid_at->format('d M Y H:i') : '' }}</div>
        @endforeach
    </div></div>
@endsection

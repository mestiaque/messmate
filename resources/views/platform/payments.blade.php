@extends('layouts.app')
@section('title', 'Payments')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Payments</h1>
        <form method="GET"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status">
            <option value="">All</option>@foreach (['pending', 'paid', 'failed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
        </select></form>
    </div>
    <div class="card border-0 shadow-sm mb-4"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Organization</th><th>Invoice</th><th>Method</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
        @forelse ($payments as $p)
            <tr>
                <td class="small text-nowrap">{{ $p->created_at->format('d M Y H:i') }}</td>
                <td><a href="{{ route('platform.tenants.show', $p->tenant_id) }}">{{ $p->tenant->name }}</a></td>
                <td class="small">{{ $p->invoice->invoice_number }}<div class="text-muted">{{ $p->invoice->description }}</div></td>
                <td class="small">{{ $p->payment_method }}<div class="text-muted">{{ $p->transaction_reference ?: $p->provider_reference }}</div></td>
                <td class="text-end">{{ $p->currency }} {{ number_format($p->amount, 2) }}</td>
                <td><span class="badge text-bg-{{ ['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger'][$p->status] }}">{{ $p->status }}</span></td>
                <td class="text-end text-nowrap">
                    @if ($p->status === 'pending' && $p->payment_method === 'manual')
                        @include('finance._decide', ['action' => route('platform.payments.decide', $p), 'value' => 'confirm', 'label' => 'Confirm', 'class' => 'btn-outline-success'])
                        @include('finance._decide', ['action' => route('platform.payments.decide', $p), 'value' => 'reject', 'label' => 'Reject', 'class' => 'btn-outline-danger', 'prompt' => 'Why is this payment rejected?'])
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No payments.</td></tr>
        @endforelse
    </table></div></div>
    <div class="mb-4">{{ $payments->links() }}</div>
    <h2 class="h5">Recent invoices</h2>
    <div class="card border-0 shadow-sm"><table class="table table-sm mb-0">
        @foreach ($invoices as $inv)
            <tr><td>{{ $inv->invoice_number }}</td><td>{{ $inv->tenant->name }}</td><td class="small">{{ $inv->description }}</td><td class="text-end">{{ number_format($inv->total, 2) }}</td><td>{{ $inv->status }}</td></tr>
        @endforeach
    </table></div>
@endsection

@extends('layouts.app')
@section('title', 'Ledger')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">General ledger</h1>
        <form method="GET"><select name="account" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Account">
            <option value="">All accounts</option>
            @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->name }}</option>@endforeach
        </select></form>
    </div>
    @forelse ($transactions as $tx)
        <div class="card border-0 shadow-sm mb-2">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2 small">
                <span><strong>{{ $tx->transaction_number }}</strong> · {{ $tx->transaction_date->format('d M Y') }} · {{ $tx->description }}</span>
                <span class="text-muted">{{ $tx->creator?->name }} @if ($tx->status === 'reversed')<span class="badge text-bg-dark">reversed</span>@endif @if ($tx->reverses_transaction_id)<span class="badge text-bg-secondary">reversal</span>@endif</span>
            </div>
            <table class="table table-sm mb-0">
                @foreach ($tx->lines as $line)
                    <tr><td class="{{ $line->credit > 0 ? 'ps-4' : '' }}">{{ $line->account->name }}@if ($line->member) <span class="text-muted small">· {{ $line->member->full_name }}</span>@endif</td>
                        <td class="text-end" style="width: 9rem">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="text-end" style="width: 9rem">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td></tr>
                @endforeach
            </table>
        </div>
    @empty
        <div class="text-center text-muted py-5">Nothing posted yet. Approved bazar, expenses and deposits appear here.</div>
    @endforelse
    <div class="mt-3">{{ $transactions->links() }}</div>
@endsection

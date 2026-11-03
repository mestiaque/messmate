@extends('layouts.app')
@section('title', 'Balances')
@section('content')
    @php($balanced = bccomp($totals['debit'], $totals['credit'], 2) === 0)
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Balances</h1>
        <span class="badge text-bg-{{ $balanced ? 'success' : 'danger' }} fs-6">
            Ledger {{ $balanced ? 'balanced' : 'OUT OF BALANCE' }}: Dr {{ number_format($totals['debit'], 2) }} / Cr {{ number_format($totals['credit'], 2) }}
        </span>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Accounts</div>
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Account</th><th>Type</th><th class="text-end">Balance</th></tr></thead>
                    @foreach ($accounts as $row)
                        <tr><td><a href="{{ route('ledger.index', ['account' => $row['account']->id]) }}">{{ $row['account']->name }}</a></td>
                            <td class="small text-muted">{{ str_replace('_', ' ', $row['account']->account_type->value) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($row['balance'], 2) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Member balances <span class="small text-muted fw-normal">(deposits + purchases they paid; meal charges post at settlement)</span></div>
                <table class="table align-middle mb-0">
                    @foreach ($members as $m)
                        <tr><td><span class="text-muted small">{{ $m->member_code }}</span> {{ $m->full_name }}</td>
                            <td class="text-end fw-semibold {{ $m->balance < 0 ? 'text-danger' : '' }}">{{ number_format($m->balance, 2) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
@endsection

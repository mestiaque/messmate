@extends('layouts.app')
@section('title', 'Deposits')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Deposits</h1>
        <form method="GET" class="d-flex flex-wrap gap-2">
            <select name="member" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto" aria-label="Member">
                <option value="">All members</option>
                @foreach ($members as $m)<option value="{{ $m->id }}" @selected(request('member') == $m->id)>{{ $m->full_name }}</option>@endforeach
            </select>
            <select name="period" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto" aria-label="Period">
                <option value="">All periods</option>
                @foreach ($periods as $p)<option value="{{ $p->id }}" @selected(request('period') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (\App\Enums\DepositStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ ucfirst($s->value) }}</option>@endforeach
            </select>
        </form>
    </div>

    @if ($canManage)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Record a deposit</div>
            <div class="card-body">
                <form method="POST" action="{{ route('deposits.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <div class="col-md-3"><label class="form-label small" for="member_id">Member</label>
                        <select id="member_id" name="member_id" class="form-select" required>
                            @foreach ($members as $m)<option value="{{ $m->id }}" @selected(old('member_id') == $m->id)>{{ $m->member_code }} · {{ $m->full_name }}</option>@endforeach
                        </select></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="amount">Amount</label><input id="amount" name="amount" value="{{ old('amount') }}" class="form-control" inputmode="decimal" required></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="payment_method">Method</label>
                        <select id="payment_method" name="payment_method" class="form-select">
                            @foreach (\App\Enums\PaymentMethod::forDeposits() as $pm)<option value="{{ $pm->value }}" @selected(old('payment_method') === $pm->value)>{{ $pm->label() }}</option>@endforeach
                        </select></div>
                    <div class="col-md-2"><label class="form-label small" for="transaction_reference">Txn ID</label><input id="transaction_reference" name="transaction_reference" value="{{ old('transaction_reference') }}" class="form-control" placeholder="bKash / bank ref"></div>
                    <div class="col-md-3"><label class="form-label small" for="paid_at">Received on</label><input id="paid_at" type="date" name="paid_at" value="{{ old('paid_at', now($mess->timezone)->toDateString()) }}" class="form-control" required></div>
                    <div class="col-md-5"><input name="notes" value="{{ old('notes') }}" class="form-control form-control-sm" placeholder="Notes (optional)"></div>
                    <div class="col-md-4"><input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control form-control-sm" aria-label="Receipt"></div>
                    <div class="col-md-3 d-flex gap-2 align-items-center">
                        @if ($canApprove)
                            <input type="hidden" name="approve" value="0">
                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="approve" value="1" id="d-approve" checked><label class="form-check-label small" for="d-approve">Approve now</label></div>
                        @endif
                        <button class="btn btn-primary ms-auto">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Member</th><th class="text-end">Amount</th><th class="d-none d-md-table-cell">Method</th><th class="d-none d-md-table-cell">Reference</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($deposits as $d)
                    <tr>
                        <td class="small text-nowrap">{{ $d->paid_at->format('d M') }}</td>
                        <td>{{ $d->member->full_name }}@if ($d->rejection_reason)<div class="small text-muted">{{ $d->rejection_reason }}</div>@endif</td>
                        <td class="text-end fw-semibold">{{ number_format($d->amount, 2) }}</td>
                        <td class="small d-none d-md-table-cell">{{ $d->payment_method->label() }}</td>
                        <td class="small d-none d-md-table-cell">{{ $d->transaction_reference }}@if ($d->receipt_path) <a href="{{ route('receipts.show', ['type' => 'deposit', 'id' => $d->id]) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endif</td>
                        <td>@include('finance._status', ['status' => $d->status])</td>
                        <td class="text-end text-nowrap">
                            @if ($canApprove && $d->status->value === 'pending' && $d->period->status->acceptsApprovals())
                                @include('finance._decide', ['action' => route('deposits.decide', ['deposit' => $d]), 'value' => 'approve', 'label' => 'Approve', 'class' => 'btn-outline-success'])
                                @include('finance._decide', ['action' => route('deposits.decide', ['deposit' => $d]), 'value' => 'reject', 'label' => 'Reject', 'class' => 'btn-outline-danger', 'prompt' => 'Reason for rejecting?'])
                            @elseif ($canApprove && $d->status->value === 'approved' && $d->period->status->acceptsEntries())
                                @include('finance._decide', ['action' => route('deposits.decide', ['deposit' => $d]), 'value' => 'reverse', 'label' => 'Reverse', 'class' => 'btn-outline-dark', 'prompt' => 'Reason for reversing? A reversal entry will be posted.'])
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No deposits.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $deposits->links() }}</div>
@endsection

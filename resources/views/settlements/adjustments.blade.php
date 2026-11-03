@extends('layouts.app')
@section('title', 'Credits & debits')
@section('content')
    <h1 class="h4 mb-1">Member credits & debits</h1>
    <p class="text-muted small">Credit = the mess owes the member more (e.g. compensation). Debit = the member owes more (e.g. a fine). Included in the period's settlement.</p>
    @if ($canManage)
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            @if ($periods->isEmpty())
                <p class="text-muted mb-0">No open period.</p>
            @else
                <form method="POST" action="{{ route('financial-adjustments.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-2"><label class="form-label small" for="period_id">Period</label>
                        <select id="period_id" name="period_id" class="form-select">@foreach ($periods as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label small" for="member_id">Member</label>
                        <select id="member_id" name="member_id" class="form-select">@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->member_code }} · {{ $m->full_name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small" for="adjustment_type">Type</label>
                        <select id="adjustment_type" name="adjustment_type" class="form-select"><option value="credit">Credit</option><option value="debit">Debit</option></select></div>
                    <div class="col-md-2"><label class="form-label small" for="amount">Amount</label><input id="amount" name="amount" class="form-control" inputmode="decimal" required></div>
                    <div class="col-md-3"><label class="form-label small" for="reason">Reason</label><input id="reason" name="reason" class="form-control" required></div>
                    <div class="col-12 d-flex gap-2 align-items-center justify-content-end">
                        <input type="hidden" name="approve" value="0">
                        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="approve" value="1" id="fa-approve" checked><label class="form-check-label small" for="fa-approve">Approve now</label></div>
                        <button class="btn btn-primary">Save</button>
                    </div>
                </form>
            @endif
        </div></div>
    @endif
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Period</th><th>Member</th><th>Type</th><th class="text-end">Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($adjustments as $a)
                    <tr>
                        <td class="small">{{ $a->period->name }}</td><td>{{ $a->member->full_name }}</td>
                        <td><span class="badge text-bg-{{ $a->adjustment_type === 'credit' ? 'success' : 'danger' }}">{{ $a->adjustment_type }}</span></td>
                        <td class="text-end">{{ number_format($a->amount, 2) }}</td><td class="small">{{ $a->reason }}</td>
                        <td>@include('finance._status', ['status' => $a->status])</td>
                        <td class="text-end text-nowrap">
                            @if ($canManage && $a->status->value === 'submitted')
                                @include('finance._decide', ['action' => route('financial-adjustments.decide', ['financialAdjustment' => $a]), 'value' => 'approve', 'label' => 'Approve', 'class' => 'btn-outline-success'])
                            @endif
                            @if ($canManage && $a->status->value !== 'voided' && $a->period->status->acceptsEntries())
                                @include('finance._decide', ['action' => route('financial-adjustments.decide', ['financialAdjustment' => $a]), 'value' => 'void', 'label' => 'Void', 'class' => 'btn-outline-dark', 'prompt' => 'Reason for voiding?'])
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No adjustments.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $adjustments->links() }}</div>
@endsection

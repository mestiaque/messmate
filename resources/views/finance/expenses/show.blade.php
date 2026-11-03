@extends('layouts.app')
@section('title', $expense->title)
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $expense->title }} @include('finance._status', ['status' => $expense->status])</h1>
            <div class="small text-muted">{{ $expense->expense_date->format('D, d M Y') }} · {{ $expense->category->name }} · {{ $expense->payment_method->label() }}{{ $expense->paidBy ? ' · '.$expense->paidBy->full_name : '' }} · {{ $expense->period->name }}</div>
            @if ($expense->rejection_reason)<div class="small text-danger mt-1">Reason: {{ $expense->rejection_reason }}</div>@endif
        </div>
        <div class="d-flex flex-wrap gap-1">
            @if ($canManage && $expense->isEditable() && $expense->period->status->acceptsEntries())
                <a href="{{ route('expenses.edit', ['expense' => $expense]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
            @endif
            @if ($canApprove && $expense->status->value === 'submitted' && $expense->period->status->acceptsApprovals())
                @include('finance._decide', ['action' => route('expenses.decide', ['expense' => $expense]), 'value' => 'approve', 'label' => 'Approve', 'class' => 'btn-success'])
                @include('finance._decide', ['action' => route('expenses.decide', ['expense' => $expense]), 'value' => 'reject', 'label' => 'Reject', 'class' => 'btn-outline-danger', 'prompt' => 'Reason for rejecting?'])
            @endif
            @if ($canManage && $expense->status->value !== 'voided' && $expense->period->status->acceptsEntries())
                @include('finance._decide', ['action' => route('expenses.decide', ['expense' => $expense]), 'value' => 'void', 'label' => 'Void', 'class' => 'btn-outline-dark', 'prompt' => 'Reason for voiding?'])
            @endif
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm stat-card"><div class="card-body">
            <div class="text-muted small">Amount</div><div class="value">{{ number_format($expense->amount, 2) }}</div>
            <div class="small text-muted">Split: {{ str_replace('_', ' ', $expense->allocation_method->value) }}</div>
            @if ($expense->receipt_path)<a class="small" href="{{ route('receipts.show', ['type' => 'expense', 'id' => $expense->id]) }}" target="_blank">View receipt</a>@endif
        </div></div></div>
        <div class="col-md-8">
            @if ($expense->allocations->isNotEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Member shares</div>
                    <table class="table table-sm mb-0">
                        @foreach ($expense->allocations as $a)
                            <tr><td>{{ $a->member->full_name }}</td><td class="text-muted small">{{ $a->allocation_basis !== null ? 'usage '.rtrim(rtrim($a->allocation_basis, '0'), '.') : '' }}</td><td class="text-end">{{ number_format($a->allocated_amount, 2) }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>
    @if ($expense->description)<p class="mt-3">{{ $expense->description }}</p>@endif
    @include('finance._ledger_tx', ['tx' => $ledger])
@endsection

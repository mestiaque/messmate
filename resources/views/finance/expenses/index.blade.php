@extends('layouts.app')
@section('title', 'Expenses')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Expenses</h1>
        <div class="d-flex flex-wrap gap-2">
            @include('finance._filters', ['statuses' => ['submitted', 'approved', 'rejected', 'voided']])
            @if ($canManage)<a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New expense</a>@endif
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Title</th><th class="d-none d-md-table-cell">Category</th><th class="text-end">Amount</th><th class="d-none d-md-table-cell">Split</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="small text-nowrap">{{ $expense->expense_date->format('d M') }}</td>
                        <td>{{ $expense->title }}</td>
                        <td class="small d-none d-md-table-cell">{{ $expense->category->name }}</td>
                        <td class="text-end fw-semibold">{{ number_format($expense->amount, 2) }}</td>
                        <td class="small d-none d-md-table-cell">{{ str_replace('_', ' ', $expense->allocation_method->value) }}</td>
                        <td>@include('finance._status', ['status' => $expense->status])</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('expenses.show', ['expense' => $expense]) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            @if ($canApprove && $expense->status->value === 'submitted' && $expense->period->status->acceptsApprovals())
                                @include('finance._decide', ['action' => route('expenses.decide', ['expense' => $expense]), 'value' => 'approve', 'label' => 'Approve', 'class' => 'btn-outline-success'])
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No expenses.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $expenses->links() }}</div>
@endsection

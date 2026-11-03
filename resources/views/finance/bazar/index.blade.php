@extends('layouts.app')
@section('title', 'Bazar')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Bazar purchases</h1>
        <div class="d-flex flex-wrap gap-2">
            @include('finance._filters', ['statuses' => ['submitted', 'approved', 'rejected', 'voided']])
            @if ($canManage)<a href="{{ route('bazar.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New purchase</a>@endif
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Purchaser</th><th class="d-none d-md-table-cell">Description</th><th class="text-end">Total</th><th class="text-end d-none d-md-table-cell">Food</th><th class="d-none d-lg-table-cell">Paid by</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="small text-nowrap">{{ $entry->purchase_date->format('d M') }}</td>
                        <td>{{ $entry->purchaser->full_name }}</td>
                        <td class="small d-none d-md-table-cell">{{ $entry->description ?: ($entry->entry_mode === 'total' ? $entry->category?->name : 'Itemized') }}</td>
                        <td class="text-end fw-semibold">{{ number_format($entry->total_amount, 2) }}</td>
                        <td class="text-end small d-none d-md-table-cell">{{ number_format($entry->food_amount, 2) }}</td>
                        <td class="small d-none d-lg-table-cell">{{ $entry->payment_method->label() }}</td>
                        <td>@include('finance._status', ['status' => $entry->status])</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('bazar.show', ['bazarEntry' => $entry]) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            @if ($canApprove && $entry->status->value === 'submitted' && $entry->period->status->acceptsApprovals())
                                @include('finance._decide', ['action' => route('bazar.decide', ['bazarEntry' => $entry]), 'value' => 'approve', 'label' => 'Approve', 'class' => 'btn-outline-success'])
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchases.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $entries->links() }}</div>
@endsection

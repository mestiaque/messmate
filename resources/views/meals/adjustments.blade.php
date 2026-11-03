@extends('layouts.app')
@section('title', 'Meal adjustments')
@section('content')
    @php($fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0')
    <h1 class="h4 mb-1">Meal adjustments</h1>
    <p class="text-muted small">Add or remove weighted units for a member within a period (e.g. a missed correction). Use negative numbers to reduce.</p>

    @if ($canManage)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                @if ($periods->isEmpty())
                    <p class="text-muted mb-0">No open period. Open a period to record adjustments.</p>
                @else
                    <form method="POST" action="{{ route('meals.adjustments.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-2"><label class="form-label small" for="period_id">Period</label>
                            <select id="period_id" name="period_id" class="form-select">@foreach ($periods as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label small" for="member_id">Member</label>
                            <select id="member_id" name="member_id" class="form-select">@foreach ($members as $m)<option value="{{ $m->id }}" @selected(old('member_id') == $m->id)>{{ $m->member_code }} · {{ $m->full_name }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label small" for="adjustment_units">Units (±)</label><input id="adjustment_units" name="adjustment_units" value="{{ old('adjustment_units') }}" class="form-control" inputmode="decimal" required></div>
                        <div class="col-md-3"><label class="form-label small" for="reason">Reason</label><input id="reason" name="reason" value="{{ old('reason') }}" class="form-control" required></div>
                        <div class="col-md-2 d-flex gap-2 align-items-center">
                            @if ($canApprove)
                                <input type="hidden" name="approve" value="0">
                                <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="approve" value="1" id="a-approve" checked><label class="form-check-label small" for="a-approve">Approve</label></div>
                            @endif
                            <button class="btn btn-primary ms-auto">Add</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Period</th><th>Member</th><th class="text-end">Units</th><th>Reason</th><th>By</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($adjustments as $adj)
                    <tr>
                        <td class="small">{{ $adj->period->name }}</td>
                        <td>{{ $adj->member->full_name }}</td>
                        <td class="text-end">{{ $adj->adjustment_units > 0 ? '+' : '' }}{{ $fmt($adj->adjustment_units) }}</td>
                        <td class="small">{{ $adj->reason }}</td>
                        <td class="small">{{ $adj->creator?->name }}</td>
                        <td><span class="badge text-bg-{{ $adj->status->badge() }}">{{ $adj->status->value }}</span></td>
                        <td>@include('meals._void', ['type' => 'adjustments', 'record' => $adj])</td>
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

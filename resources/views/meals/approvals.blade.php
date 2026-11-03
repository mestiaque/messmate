@extends('layouts.app')
@section('title', 'Pending approvals')
@section('content')
    @php($fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0')
    @php($total = $entries->count() + $guests->count() + $adjustments->count())
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Pending approvals <span class="badge text-bg-warning">{{ $total }}</span></h1>
        @if ($total)
            <button class="btn btn-success" form="bulk"><i class="bi bi-check2-all"></i> Approve selected</button>
        @endif
    </div>
    <form id="bulk" method="POST" action="{{ route('meals.approvals.bulk') }}">@csrf</form>

    @if (! $total)
        <div class="text-center text-muted py-5"><i class="bi bi-check2-circle fs-1 d-block mb-2"></i>Nothing waiting for approval.</div>
    @endif

    @foreach ([
        ['title' => 'Meal entries', 'type' => 'meal-entries', 'rows' => $entries],
        ['title' => 'Guest meals', 'type' => 'guest-meals', 'rows' => $guests],
        ['title' => 'Adjustments', 'type' => 'adjustments', 'rows' => $adjustments],
    ] as $group)
        @continue($group['rows']->isEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input js-all" data-group="{{ $group['type'] }}" aria-label="Select all {{ $group['title'] }}">
                {{ $group['title'] }} <span class="text-muted small">({{ $group['rows']->count() }})</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr>
                            <td style="width: 2rem"><input type="checkbox" class="form-check-input js-{{ $group['type'] }}" form="bulk" name="ids[]" value="{{ $group['type'] }}:{{ $row->id }}" aria-label="Select"></td>
                            <td>
                                @if ($group['type'] === 'meal-entries')
                                    <strong>{{ $row->member->full_name }}</strong> · {{ $row->meal_date->format('D d M') }}
                                    <div class="small text-muted">
                                        @if ($row->tracking_mode->value === 'individual')
                                            {{ $row->items->map(fn ($i) => $i->mealType->name.' ×'.$fmt($i->quantity))->join(', ') }}
                                        @else
                                            Daily total
                                        @endif
                                        = {{ $fmt($row->total_units) }} units · by {{ $row->creator?->name }}
                                    </div>
                                @elseif ($group['type'] === 'guest-meals')
                                    <strong>{{ $row->guest_name }}</strong> · {{ $row->meal_date->format('D d M') }}
                                    <div class="small text-muted">{{ $fmt($row->weighted_units) }} units{{ $row->sponsor ? ' · sponsor '.$row->sponsor->full_name : '' }} · by {{ $row->creator?->name }}</div>
                                @else
                                    <strong>{{ $row->member->full_name }}</strong> · {{ $row->period->name }}
                                    <div class="small text-muted">{{ $row->adjustment_units > 0 ? '+' : '' }}{{ $fmt($row->adjustment_units) }} units · {{ $row->reason }} · by {{ $row->creator?->name }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('meals.approvals.decide', ['type' => $group['type'], 'id' => $row->id]) }}" class="d-inline">
                                    @csrf <input type="hidden" name="action" value="approve">
                                    <button class="btn btn-sm btn-outline-success">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('meals.approvals.decide', ['type' => $group['type'], 'id' => $row->id]) }}" class="d-inline"
                                      onsubmit="const r = prompt('Reason for rejecting?'); if (! r) return false; this.reason.value = r;">
                                    @csrf <input type="hidden" name="action" value="reject"><input type="hidden" name="reason">
                                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
    <script>
        document.querySelectorAll('.js-all').forEach(all => all.addEventListener('change', () =>
            document.querySelectorAll('.js-' + all.dataset.group).forEach(b => b.checked = all.checked)));
    </script>
@endsection

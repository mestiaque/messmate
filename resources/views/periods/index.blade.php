@extends('layouts.app')
@section('title', 'Accounting periods')
@section('content')
    <h1 class="h4 mb-3">Accounting periods</h1>

    @if ($canManage)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">New period</div>
            <div class="card-body">
                @php($type = old('period_type', $mess->default_period_mode->value))
                <form method="POST" action="{{ route('periods.store') }}" class="row g-2 align-items-end" id="period-form">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label small" for="period_type">Type</label>
                        <select id="period_type" name="period_type" class="form-select">
                            <option value="calendar" @selected($type === 'calendar')>Calendar month</option>
                            <option value="custom" @selected($type === 'custom')>Custom range</option>
                        </select>
                    </div>
                    <div class="col-md-2 js-calendar">
                        <label class="form-label small" for="month">Month</label>
                        <input id="month" type="month" name="month" value="{{ old('month', now($mess->timezone)->format('Y-m')) }}" class="form-control">
                    </div>
                    <div class="col-md-2 js-custom">
                        <label class="form-label small" for="start_date">Start</label>
                        <input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}" class="form-control">
                    </div>
                    <div class="col-md-2 js-custom">
                        <label class="form-label small" for="end_date">End</label>
                        <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="name">Name <span class="text-muted">(optional)</span></label>
                        <input id="name" name="name" value="{{ old('name') }}" class="form-control">
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Create</button></div>
                </form>
            </div>
        </div>
        <script>
            (() => {
                const select = document.getElementById('period_type');
                const sync = () => {
                    document.querySelectorAll('#period-form .js-calendar').forEach(el => el.classList.toggle('d-none', select.value !== 'calendar'));
                    document.querySelectorAll('#period-form .js-custom').forEach(el => el.classList.toggle('d-none', select.value !== 'custom'));
                };
                select.addEventListener('change', sync);
                sync();
            })();
        </script>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Dates</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($periods as $period)
                    <tr>
                        <td>{{ $period->name }}<div class="small text-muted">{{ ucfirst($period->period_type->value) }}</div></td>
                        <td class="small">{{ $period->start_date->format('d M Y') }} – {{ $period->end_date->format('d M Y') }}</td>
                        <td><span class="badge text-bg-{{ $period->status->badge() }}">{{ ucfirst($period->status->value) }}</span></td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                @if ($canManage)
                                    @if ($period->status->value === 'reviewing')
                                        <a href="{{ route('settlements.index', ['period' => $period->id]) }}" class="btn btn-sm btn-primary">Settle & finalize</a>
                                    @endif
                                    @foreach (array_filter($period->status->allowedTransitions(), fn ($s) => $s->value !== 'finalized') as $next)
                                        <form method="POST" action="{{ route('periods.transition', ['period' => $period]) }}"
                                              @if (in_array($next->value, ['finalized', 'locked', 'archived'])) onsubmit="return confirm('Mark {{ $period->name }} as {{ $next->value }}? Entries will no longer be editable.')" @endif>
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="{{ $next->value }}">
                                            <button class="btn btn-sm btn-outline-{{ $next->badge() === 'dark' ? 'secondary' : $next->badge() }}">
                                                {{ ['open' => $period->status->value === 'reviewing' ? 'Back to open' : 'Open', 'reviewing' => 'Start review', 'finalized' => 'Finalize', 'locked' => 'Lock', 'archived' => 'Archive'][$next->value] }}
                                            </button>
                                        </form>
                                    @endforeach
                                @endif
                                @if ($canReopen && in_array($period->status->value, ['finalized', 'locked']))
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reopen-{{ $period->id }}">Reopen…</button>
                                @endif
                            </div>
                            @if ($canReopen && in_array($period->status->value, ['finalized', 'locked']))
                                <form method="POST" action="{{ route('periods.reopen', ['period' => $period]) }}" class="collapse mt-2" id="reopen-{{ $period->id }}">
                                    @csrf
                                    <div class="input-group input-group-sm">
                                        <input name="reason" class="form-control" placeholder="Reason (recorded in audit log)" required minlength="5">
                                        <button class="btn btn-danger">Reopen</button>
                                    </div>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No periods yet. Meals can only be recorded inside an open period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $periods->links() }}</div>
@endsection

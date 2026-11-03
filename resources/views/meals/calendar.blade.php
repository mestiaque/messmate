@extends('layouts.app')
@section('title', 'Meal calendar')
@section('content')
    @php($fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0')
    @php($start = $month->copy()->startOfMonth())
    @php($gridStart = $start->copy()->startOfWeek(\Carbon\CarbonInterface::SATURDAY))
    @php($gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::FRIDAY))
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $member ? $member->full_name.' — ' : '' }}{{ $month->format('F Y') }}</h1>
        <form method="GET" class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('meals.calendar', array_filter(['month' => $month->copy()->subMonth()->format('Y-m'), 'member' => $canViewAll ? $member?->id : null])) }}" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control" onchange="this.form.submit()" style="width: auto">
            <a class="btn btn-outline-secondary" href="{{ route('meals.calendar', array_filter(['month' => $month->copy()->addMonth()->format('Y-m'), 'member' => $canViewAll ? $member?->id : null])) }}" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
            @if ($canViewAll)
                <select name="member" class="form-select" onchange="this.form.submit()" style="width: auto">
                    <option value="">Whole mess</option>
                    @foreach ($members as $m)
                        <option value="{{ $m->id }}" @selected($member?->id === $m->id)>{{ $m->member_code }} · {{ $m->full_name }}</option>
                    @endforeach
                </select>
            @endif
        </form>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 text-center" style="table-layout: fixed; min-width: 560px">
                <thead class="table-light"><tr>@foreach (['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $d)<th class="small">{{ $d }}</th>@endforeach</tr></thead>
                <tbody>
                @for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay())
                    @if ($day->dayOfWeek === \Carbon\CarbonInterface::SATURDAY)<tr>@endif
                    @php($key = $day->toDateString())
                    @php($cell = $days[$key] ?? null)
                    <td class="p-1 {{ $day->month !== $month->month ? 'bg-light text-muted' : '' }}" style="height: 4.5rem">
                        <div class="small text-start">
                            @if ($canViewAll && $day->month === $month->month)
                                <a href="{{ route('meals.daily', ['date' => $key]) }}" class="text-decoration-none">{{ $day->day }}</a>
                            @else
                                {{ $day->day }}
                            @endif
                        </div>
                        @if ($cell)
                            <div class="fw-semibold">{{ $fmt($cell['units']) }}</div>
                            @if ($cell['pending'])<span class="badge text-bg-warning" title="Pending approval">{{ $cell['pending'] }} pending</span>@endif
                        @endif
                    </td>
                    @if ($day->dayOfWeek === \Carbon\CarbonInterface::FRIDAY)</tr>@endif
                @endfor
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            Month total: <strong>{{ $fmt(\App\Support\Decimal::sum(array_column($days, 'units'))) }}</strong> weighted units (submitted + approved).
        </div>
    </div>
@endsection

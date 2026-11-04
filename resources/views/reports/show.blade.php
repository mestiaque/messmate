@extends('layouts.app')
@section('title', $report['title'] ?? $label)
@section('content')
    {{-- Money (2 dp) gets thousands separators; units/counts drop trailing zeros. --}}
    @php($show = fn ($v) => ! is_numeric($v) ? $v : (str_contains($v, '.') && strlen(substr(strrchr($v, '.'), 1)) === 2 ? number_format((float) $v, 2) : (rtrim(rtrim($v, '0'), '.') ?: '0')))
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-print-none">
        <a href="{{ route('reports.index') }}" class="small"><i class="bi bi-arrow-left"></i> Reports</a>
        <form method="GET" class="d-flex flex-wrap gap-2">
            @if ($needs === 'period')
                <select name="period" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Period" style="width: auto">
                    @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->name }}</option>@endforeach
                </select>
            @elseif ($needs === 'date')
                <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Date">
            @elseif ($needs === 'member' && $members->isNotEmpty() && auth()->user()->can('viewFinance', $mess))
                <select name="member" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Member" style="width: auto">
                    <option value="">Choose member…</option>
                    @foreach ($members as $m)<option value="{{ $m->id }}" @selected($member?->id === $m->id)>{{ $m->member_code }} · {{ $m->full_name }}</option>@endforeach
                </select>
            @endif
            @if ($report && $canExport)
                <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv"></i> CSV (Excel)</a>
            @endif
            @if ($report)
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
            @endif
        </form>
    </div>

    @if (! $report)
        <div class="text-muted">{{ $needs === 'member' ? 'Choose a member.' : 'No accounting period yet.' }}</div>
    @else
        <h1 class="h4 mb-0">{{ $report['title'] }}</h1>
        <p class="text-muted small">{{ $mess->name }} · {{ $report['subtitle'] }}</p>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        @foreach ($report['columns'] as $key => $col)<th class="{{ in_array($key, $report['numeric']) ? 'text-end' : '' }}">{{ $col }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            @foreach ($report['columns'] as $key => $col)
                                @php($v = $row[$key] ?? '')
                                <td class="{{ in_array($key, $report['numeric']) ? 'text-end' : '' }} {{ is_numeric($v) && $v < 0 ? 'text-danger' : '' }}">
                                    {{ in_array($key, $report['numeric']) ? $show($v) : $v }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($report['columns']) }}" class="text-center text-muted py-4">No records.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($report['rows'] && $report['totals'])
                        <tfoot class="table-light fw-semibold"><tr>
                            @foreach (array_keys($report['columns']) as $i => $key)
                                @php($t = $report['totals'][$key] ?? null)
                                <td class="{{ $t !== null ? 'text-end' : '' }}">{{ $i === 0 ? 'Total' : ($t !== null ? $show($t) : '') }}</td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
        @if ($report['totals_note'])<p class="small text-muted mt-2">{{ $report['totals_note'] }}</p>@endif
        <p class="small text-muted d-none d-print-block mt-2">Printed {{ now($mess->timezone)->format('d M Y H:i') }}</p>
    @endif
@endsection

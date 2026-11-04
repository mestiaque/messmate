{{-- Month-end progress: approve, review, finalize, collect. --}}
@php
    $status = $period->status->value;
    $reviewed = $final || in_array($status, ['reviewing', 'finalized', 'locked', 'archived'], true);
    $steps = [
        ['Approve records', $pending ? "{$pending} meal record(s) waiting" : 'Everything approved', ! $pending || $reviewed],
        ['Review', $reviewed ? 'Period is in review' : 'Freeze entries and check numbers', $reviewed],
        ['Finalize', $final ? 'Charges posted, period locked' : 'Post member charges', (bool) $final],
        ['Collect & refund', $final ? 'Record payments below' : 'After finalizing', false],
    ];
    $current = collect($steps)->search(fn ($st) => ! $st[2]);
@endphp
<div class="mm-steps mb-3 d-print-none">
    @foreach ($steps as $i => [$label, $hint, $done])
        <div class="mm-step @if($done) done @elseif($i === $current) current @endif">
            <span class="n">@if($done)<i class="bi bi-check-lg"></i>@else{{ $i + 1 }}@endif</span>
            <div><b>{{ $label }}</b><span>{{ $hint }}</span>
                @if ($i === 0 && $pending && ! $reviewed && auth()->user()->can('approveMeals', $mess))<div><a href="{{ route('meals.approvals') }}" class="small">Open approvals →</a></div>@endif
            </div>
        </div>
    @endforeach
</div>

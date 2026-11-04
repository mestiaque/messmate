{{-- One-click shortcuts for the daily routine, shown only for what the user may do. --}}
@php
    $u = auth()->user();
    $pendingMeals = $summary['pending']['Meals'] ?? 0;
    $actions = array_filter([
        $u->can('manageMeals', $mess) ? [route('meals.daily'), 'egg-fried', 'indigo', "Today's meals", now($mess->timezone)->format('D, d M')] : null,
        $u->can('manageBazar', $mess) ? [route('bazar.create'), 'basket', 'emerald', 'Add bazar', 'Market purchase'] : null,
        $u->can('manageExpenses', $mess) ? [route('expenses.create'), 'receipt', 'rose', 'Add expense', 'Rent, gas, salary…'] : null,
        $u->can('manageDeposits', $mess) ? [route('deposits.index').'#new', 'wallet2', 'sky', 'Record deposit', 'Money from a member'] : null,
        $u->can('approveMeals', $mess) ? [route('meals.approvals'), 'check2-square', 'amber', 'Approvals', $pendingMeals ? "{$pendingMeals} meal record(s) waiting" : 'Nothing waiting'] : null,
        $member && ! $u->can('manageMeals', $mess) ? [route('portal.meals'), 'egg-fried', 'indigo', 'Enter my meals', 'If self-service is on'] : null,
    ]);
@endphp
@if ($actions)
    <div class="mm-actions mb-3 d-print-none">
        @foreach ($actions as [$href, $icon, $tone, $label, $hint])
            <a href="{{ $href }}" class="mm-action"><span class="ic ic-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span><span>{{ $label }}<small>{{ $hint }}</small></span></a>
        @endforeach
    </div>
@endif

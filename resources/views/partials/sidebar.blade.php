{{-- Sidebar navigation. Each entry: [route, active pattern(s), icon, label]; groups open when one of their links is active. --}}
@php
    $u = auth()->user();
    $isActive = fn ($patterns) => collect((array) $patterns)->contains(fn ($p) => request()->routeIs($p));
    $links = fn (array $items) => collect($items)->filter()->values();

    $platform = request()->routeIs('platform.*') ? $links([
        ['platform.dashboard', 'platform.dashboard', 'speedometer2', 'Dashboard'],
        ['platform.tenants.index', 'platform.tenants.*', 'buildings', 'Organizations'],
        ['platform.plans.index', 'platform.plans.*', 'boxes', 'Plans'],
        ['platform.payments.index', 'platform.payments.*', 'credit-card', 'Payments & invoices'],
        ['platform.tickets.index', 'platform.tickets.*', 'life-preserver', 'Support tickets'],
        ['platform.announcements.index', 'platform.announcements.*', 'megaphone', 'Announcements'],
        ['platform.settings', 'platform.settings', 'gear', 'Settings'],
    ]) : collect();

    $groups = [];
    if ($mess) {
        $member = $ctx->member();
        $finance = $u->can('viewFinance', $mess);
        $groups[] = [null, null, $links([
            ['mess.dashboard', 'mess.dashboard', 'grid-1x2', 'Dashboard'],
            $member ? ['portal.index', 'portal.*', 'person-badge', 'My portal'] : null,
            $u->can('viewMembers', $mess) ? ['members.index', ['members.*', 'invitations.*'], 'people', 'Members'] : null,
        ])];
        $groups[] = ['meals', ['Meals', 'egg-fried'], $links([
            ['meals.calendar', 'meals.calendar', null, 'Calendar'],
            $u->can('viewMeals', $mess) ? ['meals.daily', 'meals.daily', null, 'Daily entry'] : null,
            $u->can('viewMeals', $mess) ? ['meals.guests', 'meals.guests', null, 'Guest meals'] : null,
            $u->can('viewMeals', $mess) ? ['meals.adjustments', 'meals.adjustments', null, 'Adjustments'] : null,
            $u->can('approveMeals', $mess) ? ['meals.approvals', 'meals.approvals', null, 'Approvals'] : null,
            ['periods.index', 'periods.*', null, 'Periods'],
            $u->can('manageMealTypes', $mess) ? ['meal-types.index', 'meal-types.*', null, 'Meal types'] : null,
        ])];
        $groups[] = ['finance', ['Finance', 'wallet2'], $links([
            ($u->can('manageBazar', $mess) || $u->can('approveBazar', $mess) || $finance) ? ['bazar.index', 'bazar.*', null, 'Bazar'] : null,
            ($u->can('manageExpenses', $mess) || $u->can('approveExpenses', $mess) || $finance) ? ['expenses.index', 'expenses.*', null, 'Expenses'] : null,
            ($u->can('manageDeposits', $mess) || $u->can('approveDeposits', $mess) || $finance) ? ['deposits.index', 'deposits.*', null, 'Deposits'] : null,
            $u->can('manageAdjustments', $mess) ? ['financial-adjustments.index', 'financial-adjustments.*', null, 'Credits & debits'] : null,
            $finance ? ['ledger.balances', 'ledger.balances', null, 'Balances'] : null,
            $finance ? ['ledger.index', 'ledger.index', null, 'Ledger'] : null,
            $u->can('manageFinanceSettings', $mess) ? ['finance.settings', 'finance.settings', null, 'Categories'] : null,
        ])];
        $groups[] = [null, null, $links([
            $finance ? ['settlements.index', 'settlements.*', 'calculator', 'Settlement'] : null,
            ($u->can('viewMeals', $mess) || $finance) ? ['reports.index', ['reports.*', 'finance.report'], 'bar-chart-line', 'Reports'] : null,
            $u->can('manageAnnouncements', $mess) ? ['announcements.index', 'announcements.*', 'megaphone', 'Announcements'] : null,
            $u->can('update', $mess) ? ['mess.edit', ['mess.edit', 'portal-settings.*'], 'sliders', 'Mess settings'] : null,
        ])];
    }

    $org = ($tenant && ! $mess) || ($tenant && $u->can('update', $tenant)) ? $links([
        ['tenant.dashboard', 'tenant.dashboard', 'buildings', 'Overview'],
        $u->can('update', $tenant) ? ['tenant.settings.edit', 'tenant.settings.*', 'gear', 'Organization settings'] : null,
        $u->can('update', $tenant) ? ['billing.index', 'billing.*', 'credit-card', 'Plan & billing'] : null,
        $u->can('update', $tenant) ? ['support.index', 'support.*', 'life-preserver', 'Support'] : null,
    ]) : collect();
@endphp

<a href="{{ route('home') }}" class="mm-brand"><span class="mm-logo"><i class="bi bi-cup-hot-fill"></i></span>{{ config('app.name') }}</a>

@if ($tenant)
    <a href="{{ route('home') }}" class="mm-context" title="Switch organization or mess">
        <small>{{ $mess ? $tenant->name : 'Organization' }}</small>
        <strong>{{ $mess?->name ?? $tenant->name }} <i class="bi bi-chevron-expand float-end opacity-50"></i></strong>
    </a>
@endif

<nav class="mm-nav">
    @if ($platform->isNotEmpty())
        <div class="mm-nav-heading">Platform</div>
        @foreach ($platform as [$route, $pattern, $icon, $label])
            <a class="nav-link @if($isActive($pattern)) active @endif" href="{{ route($route) }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
        @endforeach
    @endif

    @foreach ($groups as [$key, $title, $items])
        @continue($items->isEmpty())
        @if ($key)
            @php($open = $items->contains(fn ($i) => $isActive($i[1])))
            <button class="nav-link mm-group-toggle @unless($open) collapsed @endunless" type="button" data-bs-toggle="collapse" data-bs-target="#nav-{{ $key }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
                <i class="bi bi-{{ $title[1] }}"></i>{{ $title[0] }}<i class="bi bi-chevron-down"></i>
            </button>
            <div class="collapse mm-sub @if($open) show @endif" id="nav-{{ $key }}">
                @foreach ($items as [$route, $pattern, $icon, $label])
                    <a class="nav-link @if($isActive($pattern)) active @endif" href="{{ route($route) }}">{{ $label }}</a>
                @endforeach
            </div>
        @else
            @foreach ($items as [$route, $pattern, $icon, $label])
                <a class="nav-link @if($isActive($pattern)) active @endif" href="{{ route($route) }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
            @endforeach
        @endif
    @endforeach

    @if ($org->isNotEmpty())
        <div class="mm-nav-heading">Organization</div>
        @foreach ($org as [$route, $pattern, $icon, $label])
            <a class="nav-link @if($isActive($pattern)) active @endif" href="{{ route($route) }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
        @endforeach
    @endif

    <div class="mm-nav-heading">Account</div>
    <a class="nav-link @if(request()->routeIs('home')) active @endif" href="{{ route('home') }}"><i class="bi bi-collection"></i>All organizations</a>
    @if ($u->is_platform_admin && ! request()->routeIs('platform.*'))
        <a class="nav-link" href="{{ route('platform.dashboard') }}"><i class="bi bi-shield-lock"></i>Platform admin</a>
    @endif
</nav>

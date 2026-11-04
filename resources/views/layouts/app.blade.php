@inject('ctx', 'App\Support\Tenancy\TenantContext')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .sidebar { width: 250px; min-height: 100vh; }
        @media (min-width: 992px) { .sidebar { flex-shrink: 0; } }
        .sidebar .nav-link { color: #495057; border-radius: .375rem; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: #e9efff; color: #0d6efd; }
        .sidebar-heading { font-size: .7rem; letter-spacing: .06em; }
        .stat-card .value { font-size: 1.6rem; font-weight: 600; }
        @media print { .sidebar, .navbar, .d-print-none { display: none !important; } main { padding: 0 !important; } body { background: #fff; } .card { box-shadow: none !important; } }
    </style>
</head>
<body>
@php($tenant = $ctx->tenant())
@php($mess = $ctx->mess())
<nav class="navbar navbar-expand bg-white border-bottom px-3 sticky-top">
    <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">
        <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand fw-semibold" href="{{ route('home') }}">{{ config('app.name') }}</a>
    @if ($tenant)
        <span class="text-muted small d-none d-md-inline text-truncate">
            {{ $tenant->name }}@if ($mess) / {{ $mess->name }}@endif
        </span>
    @endif
    <div class="ms-auto dropdown">
        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-arrow-left-right me-2"></i>Switch organization / mess</a></li>
            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
            @if (auth()->user()->is_platform_admin)
                <li><a class="dropdown-item" href="{{ route('platform.dashboard') }}"><i class="bi bi-shield-lock me-2"></i>Platform admin</a></li>
            @endif
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                </form>
            </li>
        </ul>
    </div>
</nav>

<div class="d-flex">
    <aside class="offcanvas-lg offcanvas-start bg-white border-end sidebar" tabindex="-1" id="sidebar">
        <div class="offcanvas-header d-lg-none">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
        </div>
        <div class="offcanvas-body d-block p-3">
            @if (request()->routeIs('platform.*'))
                <div class="sidebar-heading text-uppercase text-muted mb-2">Platform</div>
                <ul class="nav flex-column mb-3">
                    <li><a class="nav-link @if(request()->routeIs('platform.dashboard')) active @endif" href="{{ route('platform.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                    <li><a class="nav-link @if(request()->routeIs('platform.tenants.*')) active @endif" href="{{ route('platform.tenants.index') }}"><i class="bi bi-buildings me-2"></i>Organizations</a></li>
                </ul>
            @endif
            @if ($tenant)
                <div class="sidebar-heading text-uppercase text-muted mb-2">Organization</div>
                <ul class="nav flex-column mb-3">
                    <li><a class="nav-link @if(request()->routeIs('tenant.dashboard')) active @endif" href="{{ route('tenant.dashboard') }}"><i class="bi bi-grid me-2"></i>Overview</a></li>
                    @can('update', $tenant)
                        <li><a class="nav-link @if(request()->routeIs('tenant.settings.*')) active @endif" href="{{ route('tenant.settings.edit') }}"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    @endcan
                </ul>
            @endif
            @if ($mess)
                <div class="sidebar-heading text-uppercase text-muted mb-2 text-truncate">{{ $mess->name }}</div>
                <ul class="nav flex-column mb-3">
                    <li><a class="nav-link @if(request()->routeIs('mess.dashboard')) active @endif" href="{{ route('mess.dashboard') }}"><i class="bi bi-house me-2"></i>Dashboard</a></li>
                    @can('viewMembers', $mess)
                        <li><a class="nav-link @if(request()->routeIs('members.*')) active @endif" href="{{ route('members.index') }}"><i class="bi bi-people me-2"></i>Members</a></li>
                    @endcan
                    @if ($ctx->member())
                        <li><a class="nav-link @if(request()->routeIs('portal.index')) active @endif" href="{{ route('portal.index') }}"><i class="bi bi-person-badge me-2"></i>My portal</a></li>
                        <li><a class="nav-link @if(request()->routeIs('members.show') && request()->route('member')?->id === $ctx->member()->id) active @endif" href="{{ route('members.show', ['member' => $ctx->member()]) }}"><i class="bi bi-person-vcard me-2"></i>My meals</a></li>
                    @endif
                    <li><a class="nav-link @if(request()->routeIs('meals.calendar')) active @endif" href="{{ route('meals.calendar') }}"><i class="bi bi-calendar3 me-2"></i>Meal calendar</a></li>
                    @can('viewMeals', $mess)
                        <li><a class="nav-link @if(request()->routeIs('meals.daily')) active @endif" href="{{ route('meals.daily') }}"><i class="bi bi-egg-fried me-2"></i>Daily meals</a></li>
                        <li><a class="nav-link @if(request()->routeIs('meals.guests')) active @endif" href="{{ route('meals.guests') }}"><i class="bi bi-person-plus me-2"></i>Guest meals</a></li>
                        <li><a class="nav-link @if(request()->routeIs('meals.adjustments')) active @endif" href="{{ route('meals.adjustments') }}"><i class="bi bi-plus-slash-minus me-2"></i>Adjustments</a></li>
                    @endcan
                    @can('approveMeals', $mess)
                        <li><a class="nav-link @if(request()->routeIs('meals.approvals')) active @endif" href="{{ route('meals.approvals') }}"><i class="bi bi-check2-square me-2"></i>Approvals</a></li>
                    @endcan
                    <li><a class="nav-link @if(request()->routeIs('periods.*')) active @endif" href="{{ route('periods.index') }}"><i class="bi bi-calendar-range me-2"></i>Periods</a></li>
                    @can('manageMealTypes', $mess)
                        <li><a class="nav-link @if(request()->routeIs('meal-types.*')) active @endif" href="{{ route('meal-types.index') }}"><i class="bi bi-list-ol me-2"></i>Meal types</a></li>
                    @endcan
                    @if (auth()->user()->can('viewMeals', $mess) || auth()->user()->can('viewFinance', $mess))
                        <li><a class="nav-link @if(request()->routeIs('reports.*')) active @endif" href="{{ route('reports.index') }}"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Reports</a></li>
                    @endif
                    @can('manageAnnouncements', $mess)
                        <li><a class="nav-link @if(request()->routeIs('announcements.*')) active @endif" href="{{ route('announcements.index') }}"><i class="bi bi-megaphone me-2"></i>Announcements</a></li>
                    @endcan
                    @can('update', $mess)
                        <li><a class="nav-link @if(request()->routeIs('mess.edit')) active @endif" href="{{ route('mess.edit') }}"><i class="bi bi-sliders me-2"></i>Mess settings</a></li>
                    @endcan
                </ul>
                @php($u = auth()->user())
                @if ($u->can('manageBazar', $mess) || $u->can('approveBazar', $mess) || $u->can('manageExpenses', $mess) || $u->can('manageDeposits', $mess) || $u->can('viewFinance', $mess))
                    <div class="sidebar-heading text-uppercase text-muted mb-2">Finance</div>
                    <ul class="nav flex-column mb-3">
                        @if ($u->can('manageBazar', $mess) || $u->can('approveBazar', $mess) || $u->can('viewFinance', $mess))
                            <li><a class="nav-link @if(request()->routeIs('bazar.*')) active @endif" href="{{ route('bazar.index') }}"><i class="bi bi-basket me-2"></i>Bazar</a></li>
                        @endif
                        @if ($u->can('manageExpenses', $mess) || $u->can('approveExpenses', $mess) || $u->can('viewFinance', $mess))
                            <li><a class="nav-link @if(request()->routeIs('expenses.*')) active @endif" href="{{ route('expenses.index') }}"><i class="bi bi-receipt me-2"></i>Expenses</a></li>
                        @endif
                        @if ($u->can('manageDeposits', $mess) || $u->can('approveDeposits', $mess) || $u->can('viewFinance', $mess))
                            <li><a class="nav-link @if(request()->routeIs('deposits.*')) active @endif" href="{{ route('deposits.index') }}"><i class="bi bi-wallet2 me-2"></i>Deposits</a></li>
                        @endif
                        @can('viewFinance', $mess)
                            <li><a class="nav-link @if(request()->routeIs('settlements.*')) active @endif" href="{{ route('settlements.index') }}"><i class="bi bi-calculator me-2"></i>Settlement</a></li>
                        @endcan
                        @if ($u->can('manageAdjustments', $mess))
                            <li><a class="nav-link @if(request()->routeIs('financial-adjustments.*')) active @endif" href="{{ route('financial-adjustments.index') }}"><i class="bi bi-arrow-left-right me-2"></i>Credits & debits</a></li>
                        @endif
                        @can('viewFinance', $mess)
                            <li><a class="nav-link @if(request()->routeIs('finance.report')) active @endif" href="{{ route('finance.report') }}"><i class="bi bi-bar-chart me-2"></i>Period report</a></li>
                            <li><a class="nav-link @if(request()->routeIs('ledger.balances')) active @endif" href="{{ route('ledger.balances') }}"><i class="bi bi-cash-stack me-2"></i>Balances</a></li>
                            <li><a class="nav-link @if(request()->routeIs('ledger.index')) active @endif" href="{{ route('ledger.index') }}"><i class="bi bi-journal-text me-2"></i>Ledger</a></li>
                        @endcan
                        @can('manageFinanceSettings', $mess)
                            <li><a class="nav-link @if(request()->routeIs('finance.settings')) active @endif" href="{{ route('finance.settings') }}"><i class="bi bi-tags me-2"></i>Categories</a></li>
                        @endcan
                    </ul>
                @endif
            @endif
            <ul class="nav flex-column">
                <li><a class="nav-link @if(request()->routeIs('home')) active @endif" href="{{ route('home') }}"><i class="bi bi-collection me-2"></i>All organizations</a></li>
            </ul>
        </div>
    </aside>

    <main class="flex-grow-1 p-3 p-md-4" style="min-width: 0">
        @include('partials.flash')
        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>

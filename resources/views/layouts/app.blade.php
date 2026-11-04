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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/messmate.css') }}?v=3" rel="stylesheet">
</head>
<body>
@php($tenant = $ctx->tenant())
@php($mess = $ctx->mess())
<div class="mm-shell">
    <aside class="offcanvas-lg offcanvas-start mm-sidebar" tabindex="-1" id="sidebar" aria-label="Navigation">
        <div class="d-lg-none text-end p-2"><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button></div>
        @include('partials.sidebar')
    </aside>

    <div class="mm-main">
        <header class="mm-topbar d-print-none">
            <button class="btn btn-light btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
            <div class="mm-crumbs">
                @if ($tenant)
                    <a href="{{ route('tenant.dashboard', ['tenant' => $tenant]) }}">{{ $tenant->name }}</a>
                    @if ($mess)<span class="sep">/</span><a href="{{ route('mess.dashboard', ['tenant' => $tenant, 'mess' => $mess]) }}">{{ $mess->name }}</a>@endif
                    @if (! request()->routeIs('mess.dashboard', 'tenant.dashboard'))<span class="sep">/</span>@endif
                @elseif (request()->routeIs('platform.*') && ! request()->routeIs('platform.dashboard'))
                    <a href="{{ route('platform.dashboard') }}">Platform</a><span class="sep">/</span>
                @endif
                @unless ($tenant && request()->routeIs('mess.dashboard', 'tenant.dashboard'))<span class="text-body fw-medium">@yield('title', 'Dashboard')</span>@endunless
            </div>
            <div class="ms-auto dropdown">
                <button class="mm-user dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="mm-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-2 py-1 small text-muted text-truncate" style="max-width: 240px">{{ auth()->user()->email ?? auth()->user()->phone }}</li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-arrow-left-right me-2"></i>Switch organization / mess</a></li>
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                    @if (auth()->user()->is_platform_admin)
                        <li><a class="dropdown-item" href="{{ route('platform.dashboard') }}"><i class="bi bi-shield-lock me-2"></i>Platform admin</a></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="mm-content @hasSection('narrow') mm-narrow @endif">
        @if ($tenant)
            @inject('subscriptions', 'App\Services\Billing\SubscriptionService')
            @php($notice = $subscriptions->notice($tenant))
            @if ($notice)
                <div class="alert alert-{{ $notice[0] }} py-2 small d-flex flex-wrap justify-content-between align-items-center gap-2 d-print-none">
                    <span><i class="bi bi-{{ $notice[0] === 'info' ? 'stars' : 'exclamation-triangle' }} me-1"></i>{{ $notice[1] }}</span>
                    @if (! request()->routeIs('billing.*') && auth()->user()->can('update', $tenant))<a href="{{ route('billing.index') }}" class="alert-link">Plan & billing</a>@endif
                </div>
            @endif
            @foreach (\App\Models\PlatformAnnouncement::query()->visible()->limit(2)->get() as $pa)
                <div class="alert alert-light border py-2 small d-print-none"><i class="bi bi-megaphone me-1"></i><strong>{{ $pa->title }}</strong> — {{ $pa->body }}</div>
            @endforeach
        @endif
        @include('partials.flash')
        @yield('content')
    </main>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>

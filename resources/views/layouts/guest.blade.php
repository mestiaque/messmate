<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/messmate.css') }}?v=3" rel="stylesheet">
</head>
<body>
<div class="mm-auth">
    <section class="mm-auth-art">
        <div class="d-flex align-items-center gap-2 fw-bold fs-5 text-white"><span class="mm-logo"><i class="bi bi-cup-hot-fill"></i></span>{{ config('app.name') }}</div>
        <div>
            <h2 class="mb-4">Meals, bazar and month-end bills for your mess, without the spreadsheet.</h2>
            <ul class="list-unstyled small">
                <li><i class="bi bi-check2-circle me-2 text-info"></i>Daily meal entry by member or by total</li>
                <li><i class="bi bi-check2-circle me-2 text-info"></i>Bazar, expenses and deposits on a balanced ledger</li>
                <li><i class="bi bi-check2-circle me-2 text-info"></i>One-click settlement with a statement for every member</li>
            </ul>
        </div>
        <div class="small opacity-50">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
    </section>
    <section class="mm-auth-form">
        <div class="mm-auth-card">
            <div class="d-flex d-lg-none align-items-center gap-2 fw-bold fs-5 mb-4"><span class="mm-logo"><i class="bi bi-cup-hot-fill"></i></span>{{ config('app.name') }}</div>
            @yield('content')
        </div>
    </section>
</div>
</body>
</html>

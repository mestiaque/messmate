<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 440px">
    <h1 class="h3 text-center mb-4 fw-semibold">{{ config('app.name') }}</h1>
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>

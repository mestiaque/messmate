@extends('layouts.app')
@section('title', 'Reports')
@section('content')
    <h1 class="h4 mb-3">Reports</h1>
    <div class="row g-3">
        @foreach ($types as $type => [$label, $needs])
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('reports.show', ['type' => $type]) }}" class="card border-0 shadow-sm text-decoration-none h-100"><div class="card-body">
                    <div class="fw-semibold text-body">{{ $label }}</div>
                    <div class="small text-muted">{{ ['date' => 'For one day', 'period' => 'For an accounting period', 'member' => 'For one member, all time', 'none' => 'Current'][$needs] }} · view, print or CSV</div>
                </div></a>
            </div>
        @endforeach
        @can('viewFinance', $mess)
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('finance.report') }}" class="card border-0 shadow-sm text-decoration-none h-100"><div class="card-body">
                    <div class="fw-semibold text-body">Financial summary</div>
                    <div class="small text-muted">Bazar, expenses and deposits by category for a period · print</div>
                </div></a>
            </div>
        @endcan
    </div>
@endsection

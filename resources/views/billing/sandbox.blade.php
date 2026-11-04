@extends('layouts.guest')
@section('title', 'Sandbox payment')
@section('content')
    <div class="alert alert-warning small">Sandbox gateway — for testing only. No real money moves.</div>
    <h2 class="h5">Pay {{ $payment->currency }} {{ number_format($payment->amount, 2) }}</h2>
    <p class="small text-muted">{{ $payment->tenant->name }} · {{ $payment->invoice->description }} · {{ $payment->invoice->invoice_number }}</p>
    @if ($payment->status !== 'pending')
        <div class="alert alert-secondary">This payment is already {{ $payment->status }}.</div>
    @else
        <form method="POST" action="{{ route('billing.sandbox.pay', ['reference' => $payment->provider_reference]) }}" class="d-flex gap-2">
            @csrf
            <button name="outcome" value="paid" class="btn btn-success flex-fill">Pay</button>
            <button name="outcome" value="declined" class="btn btn-outline-danger flex-fill">Decline</button>
        </form>
    @endif
@endsection

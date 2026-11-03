<?php

namespace App\Http\Controllers\Settlement;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Services\Finance\IdempotencyService;
use App\Services\Settlement\SettlementPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettlementPaymentController extends Controller
{
    public function store(Request $request, Tenant $tenant, Mess $mess, Settlement $settlement, SettlementPaymentService $payments, IdempotencyService $idempotency): RedirectResponse
    {
        $this->authorize('paySettlements', $mess);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(array_map(fn ($m) => $m->value, PaymentMethod::forDeposits()))],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        [$fresh] = $idempotency->once($mess->tenant_id, $data['idempotency_key'] ?? null, $data + ['settlement' => $settlement->id], fn () => $payments->record(
            $settlement, (string) $data['amount'], PaymentMethod::from($data['payment_method']), $data['transaction_reference'] ?? null, $request->user(),
        ));

        return back()->with('status', $fresh ? 'Payment recorded and posted.' : 'That payment was already recorded.');
    }
}

<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use App\Services\Finance\ReceiptStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageDeposits', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(array_map(fn ($m) => $m->value, PaymentMethod::forDeposits()))],
            'paid_at' => ['required', 'date'],
            'transaction_reference' => [Rule::requiredIf(in_array($this->input('payment_method'), ['bank', 'mobile_wallet'], true)), 'nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => ReceiptStorage::RULES,
            'approve' => ['boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['transaction_reference.required' => 'Bank and mobile wallet deposits need the transaction ID.'];
    }
}

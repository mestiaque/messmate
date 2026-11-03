<?php

namespace App\Http\Requests\Finance;

use App\Enums\AllocationMethod;
use App\Enums\PaymentMethod;
use App\Services\Finance\ReceiptStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageExpenses', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'expense_date' => ['required', 'date'],
            'allocation_method' => ['required', Rule::enum(AllocationMethod::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_by_member_id' => ['nullable', 'integer'],
            'shares' => ['array'],
            'shares.*' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,4'],
            'receipt' => ReceiptStorage::RULES,
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}

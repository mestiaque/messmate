<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use App\Services\Finance\ReceiptStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BazarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageBazar', $this->route('mess'));
    }

    public function rules(): array
    {
        $itemized = $this->input('entry_mode') === 'itemized';

        return [
            'purchase_date' => ['required', 'date'],
            'purchased_by' => ['required', 'integer'],
            'entry_mode' => ['required', Rule::in(['total', 'itemized'])],
            'description' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'category_id' => [Rule::requiredIf(! $itemized), 'nullable', 'integer'],
            'amount' => [Rule::requiredIf(! $itemized), 'nullable', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'items' => [Rule::requiredIf($itemized), 'array', 'max:100'],
            'items.*.item_name' => ['required', 'string', 'max:150'],
            'items.*.category_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,4'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,4'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'receipt' => ReceiptStorage::RULES,
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Drop blank item rows left over from the form's spare lines.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('items'))) {
            $this->merge(['items' => array_values(array_filter($this->input('items'), fn ($i) => is_array($i) && trim((string) ($i['item_name'] ?? '')) !== ''))]);
        }
    }
}

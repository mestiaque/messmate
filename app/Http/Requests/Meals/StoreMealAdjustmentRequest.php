<?php

namespace App\Http\Requests\Meals;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMeals', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer'],
            'member_id' => ['required', 'integer'],
            'adjustment_units' => ['required', 'numeric', 'not_in:0', 'min:-100', 'max:100', 'decimal:0,4'],
            'reason' => ['required', 'string', 'max:255'],
            'approve' => ['boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Meals;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMeals', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:255'],
            'meal_date' => ['required', 'date'],
            'meal_type_id' => ['nullable', 'integer'],
            'sponsor_member_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,4'],
            'charge_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'approve' => ['boolean'],
        ];
    }
}

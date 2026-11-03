<?php

namespace App\Http\Requests\Meals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMealTypes', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('meal_types', 'code')->where('mess_id', $this->route('mess')->id)->ignore($this->route('mealType')),
            ],
            'unit_factor' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,6'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'default_enabled' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}

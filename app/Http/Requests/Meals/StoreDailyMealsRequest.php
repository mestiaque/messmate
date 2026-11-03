<?php

namespace App\Http\Requests\Meals;

use App\Enums\MealTrackingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyMealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMeals', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'tracking_mode' => ['required', Rule::enum(MealTrackingMode::class)],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer'],
            'meals' => ['array'],
            'meals.*' => ['array'],
            'meals.*.*' => ['nullable', 'numeric', 'min:0', 'max:50', 'decimal:0,4'],
            'units' => ['array'],
            'units.*' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'raw_count' => ['array'],
            'raw_count.*' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'conversion_factor' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,6'],
            'approve' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['selected.required' => 'Select at least one member.'];
    }
}

<?php

namespace App\Http\Requests\Meals;

use App\Enums\PeriodMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePeriods', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'period_type' => ['required', Rule::enum(PeriodMode::class)],
            'month' => ['required_if:period_type,calendar', 'nullable', 'date_format:Y-m'],
            'start_date' => ['required_if:period_type,custom', 'nullable', 'date'],
            'end_date' => ['required_if:period_type,custom', 'nullable', 'date', 'after_or_equal:start_date'],
            'name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

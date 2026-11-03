<?php

namespace App\Http\Requests\Mess;

use App\Enums\MealTrackingMode;
use App\Enums\PeriodMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createMess', $this->route('tenant'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'timezone' => ['required', 'timezone:all'],
            'default_period_mode' => ['required', Rule::enum(PeriodMode::class)],
            'default_meal_tracking_mode' => ['required', Rule::enum(MealTrackingMode::class)],
            'join_as_member' => ['sometimes', 'boolean'],
        ];
    }
}

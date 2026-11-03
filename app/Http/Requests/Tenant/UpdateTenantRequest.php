<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tenant'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
            'default_currency' => ['required', 'string', 'size:3', 'alpha'],
            'default_timezone' => ['required', 'timezone:all'],
        ];
    }
}

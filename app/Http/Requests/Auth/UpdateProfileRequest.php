<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'phone')->ignore($id)],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}

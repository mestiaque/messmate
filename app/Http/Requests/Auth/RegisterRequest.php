<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9]{8,15}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

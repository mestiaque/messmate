<?php

namespace App\Http\Requests\Mess;

use App\Http\Requests\Concerns\RestrictsRoleGrants;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    use RestrictsRoleGrants;

    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'room_number' => ['nullable', 'string', 'max:32'],
            'joined_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return $this->restrictRoleGrants('roles');
    }
}

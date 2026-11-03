<?php

namespace App\Http\Requests\Mess;

use App\Http\Requests\Concerns\RestrictsRoleGrants;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    use RestrictsRoleGrants;

    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9]{8,15}$/'],
            'role_id' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return $this->restrictRoleGrants('role_id');
    }
}

<?php

namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;

class SyncMemberRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRoles', $this->route('mess'));
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer'],
        ];
    }
}

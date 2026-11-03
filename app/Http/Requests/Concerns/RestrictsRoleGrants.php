<?php

namespace App\Http\Requests\Concerns;

use App\Models\Role;

/**
 * Users who can manage members but not roles may only grant the Member role.
 */
trait RestrictsRoleGrants
{
    protected function restrictRoleGrants(string $field): array
    {
        return [function ($validator) use ($field) {
            $mess = $this->route('mess');

            if ($this->user()->can('manageRoles', $mess)) {
                return;
            }

            $memberRole = Role::query()->assignableInMess($mess)->where('slug', Role::MEMBER)->value('id');

            if (array_diff(array_map('intval', (array) $this->input($field)), [$memberRole])) {
                $validator->errors()->add($field, 'You may only grant the Member role.');
            }
        }];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Tenancy\Permissions;
use Illuminate\Database\Seeder;

/**
 * Idempotent: safe to re-run after new permissions are added to the catalogue.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::all() as $name => $meta) {
            Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], $meta);
        }

        foreach (Permissions::systemMessRoles() as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['tenant_id' => null, 'slug' => $slug, 'scope' => 'mess'],
                ['name' => $definition['name'], 'guard_name' => 'web'],
            );

            $role->permissions()->sync(Permission::whereIn('name', $definition['permissions'])->pluck('id'));
        }
    }
}

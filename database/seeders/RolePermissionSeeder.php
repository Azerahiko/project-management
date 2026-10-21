<?php

namespace Database\Seeders;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the roles and permissions. Safe to run repeatedly.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::grouped() as $group) {
            foreach (array_keys($group) as $slug) {
                Permission::updateOrCreate(
                    ['name' => $slug, 'guard_name' => 'web'],
                );
            }
        }

        foreach (array_keys(Roles::all()) as $slug) {
            Role::updateOrCreate(
                ['name' => $slug, 'guard_name' => 'web'],
            );
        }

        foreach (Permissions::rolePermissions() as $roleSlug => $permissions) {
            $role = Role::where('name', $roleSlug)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

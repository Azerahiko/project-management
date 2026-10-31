<?php

namespace App\Http\Controllers;

use App\Http\Requests\RolePermissionRequest;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        // Group permissions by category using the Permissions support class
        $groupedPermissions = Permissions::grouped();

        // Get current permissions for each role
        $rolePermissions = [];
        foreach ($roles as $role) {
            $rolePermissions[$role->name] = $role->permissions->pluck('name')->toArray();
        }

        return Inertia::render('Roles/Permissions', [
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => Roles::all()[$role->name] ?? $role->name,
                'permissions_count' => $role->permissions->count(),
            ]),
            'permissions' => $permissions->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
            ]),
            'grouped_permissions' => $groupedPermissions,
            'role_permissions' => $rolePermissions,
        ]);
    }

    public function update(RolePermissionRequest $request, Role $role): RedirectResponse
    {
        // Prevent modification of the system administrator role
        if ($role->name === Roles::ADMINISTRATOR) {
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat mengubah permission untuk role Administrator sistem.');
        }

        $validatedPermissions = $request->validated('permissions', []);

        // Validate that all permissions exist in the database
        $existingPermissions = Permission::where('guard_name', 'web')
            ->whereIn('name', $validatedPermissions)
            ->pluck('name')
            ->toArray();

        $invalidPermissions = array_diff($validatedPermissions, $existingPermissions);
        if (! empty($invalidPermissions)) {
            return redirect()
                ->back()
                ->with('error', 'Permission tidak valid: '.implode(', ', $invalidPermissions));
        }

        // Sync permissions
        $role->syncPermissions($existingPermissions);

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->back()
            ->with('success', 'Permission untuk role "'.$role->name.'" berhasil diperbarui.');
    }
}

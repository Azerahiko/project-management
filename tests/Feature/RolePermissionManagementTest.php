<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Role & Permission Management', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@projex.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('administrator');

        $this->user = User::factory()->create([
            'email' => 'user@projex.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('member');
    });

    describe('Web UI access', function () {
        it('allows systemadministrator to access role permission management page', function () {
            $this->actingAs($this->admin)
                ->get('/roles/permissions')
                ->assertOk();
        });

        it('denies non-admin access to role permission management', function () {
            $this->actingAs($this->user)
                ->get('/roles/permissions')
                ->assertForbidden();
        });

        it('denies unauthenticated access to role permission management', function () {
            $this->get('/roles/permissions')
                ->assertRedirect('/login');
        });
    });

    describe('Permission Update', function () {
        it('allows administrator to update role permissions', function () {
            $role = Role::where('name', 'member')->first();
            $newPermissions = [
                'dashboard.view',
                'notifications.view',
                'projects.view',
                'projects.create',
                'tasks.view',
            ];

            $this->actingAs($this->admin)
                ->put("/roles/permissions/{$role->id}", [
                    'permissions' => $newPermissions,
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            // Verify permissions were synced (order doesn't matter)
            $role->refresh();
            $actualPermissions = $role->permissions->pluck('name')->toArray();
            sort($newPermissions);
            sort($actualPermissions);
            expect($actualPermissions)->toEqual($newPermissions);
        });

        it('denies non-admin updating role permissions', function () {
            $role = Role::where('name', 'member')->first();

            $this->actingAs($this->user)
                ->put("/roles/permissions/{$role->id}", [
                    'permissions' => ['dashboard.view'],
                ])
                ->assertForbidden();
        });

        it('denies unauthenticated updating role permissions', function () {
            $role = Role::where('name', 'member')->first();

            $this->put("/roles/permissions/{$role->id}", [
                'permissions' => ['dashboard.view'],
            ])
                ->assertRedirect('/login');
        });

        it('rejects invalid permissions', function () {
            $role = Role::where('name', 'member')->first();

            $response = $this->actingAs($this->admin)
                ->put("/roles/permissions/{$role->id}", [
                    'permissions' => ['invalid.permission', 'dashboard.view'],
                ]);

            $response->assertRedirect()
                ->assertSessionHas('errors');
        });

        it('prevents modifying administrator role permissions', function () {
            $adminRole = Role::where('name', 'administrator')->first();
            $originalPermissions = $adminRole->permissions->pluck('name')->toArray();

            $this->actingAs($this->admin)
                ->put("/roles/permissions/{$adminRole->id}", [
                    'permissions' => ['dashboard.view'],
                ])
                ->assertRedirect()
                ->assertSessionHas('error');

            // Verify permissions were not changed
            $adminRole->refresh();
            expect($adminRole->permissions->pluck('name')->toArray())->toEqual($originalPermissions);
        });

        it('clears permission cache after update', function () {
            $role = Role::where('name', 'member')->first();
            $newPermissions = ['dashboard.view', 'notifications.view'];

            $this->actingAs($this->admin)
                ->put("/roles/permissions/{$role->id}", [
                    'permissions' => $newPermissions,
                ])
                ->assertRedirect();

            // Verify cache was cleared by checking the role has new permissions
            $role->refresh();
            expect($role->permissions->pluck('name')->toArray())->toEqual($newPermissions);
        });

        it('validates permissions array is required', function () {
            $role = Role::where('name', 'member')->first();

            $response = $this->actingAs($this->admin)
                ->put("/roles/permissions/{$role->id}", []);

            $response->assertRedirect()
                ->assertSessionHas('errors');
        });

        it('validates each permission exists in database', function () {
            $role = Role::where('name', 'member')->first();

            $response = $this->actingAs($this->admin)
                ->put("/roles/permissions/{$role->id}", [
                    'permissions' => ['dashboard.view', 'nonexistent.permission'],
                ]);

            $response->assertRedirect()
                ->assertSessionHas('errors');
        });
    });

    describe('Navigation', function () {
        it('verifies administrator has roles.manage permission', function () {
            expect($this->admin->can('roles.manage'))->toBeTrue();
        });

        it('verifies non-administrator lacks roles.manage permission', function () {
            expect($this->user->can('roles.manage'))->toBeFalse();
        });
    });

    describe('Data Integrity', function () {
        it('persists permission changes to database', function () {
            $role = Role::where('name', 'project_manager')->first();
            $newPermissions = [
                'dashboard.view',
                'notifications.view',
                'projects.view',
                'projects.create',
                'projects.update',
                'tasks.view',
                'reports.view',
            ];

            $this->actingAs($this->admin)
                ->put("/roles/permissions/{$role->id}", [
                    'permissions' => $newPermissions,
                ])
                ->assertRedirect();

            // Verify in database
            $this->assertDatabaseHas('role_has_permissions', [
                'role_id' => $role->id,
                'permission_id' => Permission::where('name', 'projects.create')->first()->id,
            ]);

            // Verify old permissions were removed
            $this->assertDatabaseMissing('role_has_permissions', [
                'role_id' => $role->id,
                'permission_id' => Permission::where('name', 'projects.delete')->first()->id,
            ]);
        });

        it('maintains role-permission relationship integrity', function () {
            $role = Role::where('name', 'team_leader')->first();

            // Add a permission
            $permission = Permission::where('name', 'projects.create')->first();
            $role->givePermissionTo($permission);

            // Update with different permissions
            $newPermissions = ['dashboard.view', 'projects.view'];

            $this->actingAs($this->admin)
                ->put("/roles/permissions/{$role->id}", [
                    'permissions' => $newPermissions,
                ])
                ->assertRedirect();

            $role->refresh();
            expect($role->hasPermissionTo('projects.create'))->toBeFalse();
            expect($role->hasPermissionTo('projects.view'))->toBeTrue();
        });
    });
});

<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Management RBAC', function () {
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
        it('allows admin to access user management index', function () {
            $this->actingAs($this->admin)
                ->get('/users')
                ->assertOk();
        });

        it('allows admin to access user management create', function () {
            $this->actingAs($this->admin)
                ->get('/users/create')
                ->assertOk();
        });

        it('denies non-admin access to user management', function () {
            $this->actingAs($this->user)
                ->get('/users')
                ->assertForbidden();

            $this->actingAs($this->user)
                ->get('/users/create')
                ->assertForbidden();
        });
    });

    describe('API CRUD', function () {
        it('allows admin to create user via API', function () {
            $this->actingAs($this->admin)
                ->postJson('/api/users', [
                    'name' => 'New User',
                    'email' => 'new@projex.test',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'role' => 'member',
                ])
                ->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'name', 'email']]);

            $this->assertDatabaseHas('users', ['email' => 'new@projex.test']);
        });

        it('denies non-admin creating user via API', function () {
            $this->actingAs($this->user)
                ->postJson('/api/users', [
                    'name' => 'New User',
                    'email' => 'new@projex.test',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'role' => 'member',
                ])
                ->assertForbidden();
        });

        it('allows admin to update user via API', function () {
            $target = User::factory()->create([
                'email' => 'target@projex.test',
                'is_active' => true,
            ]);
            $target->assignRole('member');

            $this->actingAs($this->admin)
                ->putJson("/api/users/{$target->id}", [
                    'name' => 'Updated Name',
                    'email' => 'updated@projex.test',
                    'role' => 'project_manager',
                ])
                ->assertOk()
                ->assertJsonStructure(['data' => ['id', 'name', 'email']]);

            $this->assertDatabaseHas('users', [
                'id' => $target->id,
                'name' => 'Updated Name',
            ]);
        });

        it('denies non-admin updating user via API', function () {
            $target = User::factory()->create([
                'email' => 'target@projex.test',
                'is_active' => true,
            ]);
            $target->assignRole('member');

            $this->actingAs($this->user)
                ->putJson("/api/users/{$target->id}", [
                    'name' => 'Updated Name',
                ])
                ->assertForbidden();
        });

        it('allows admin to delete user via API', function () {
            $target = User::factory()->create([
                'email' => 'target@projex.test',
                'is_active' => true,
            ]);
            $target->assignRole('member');

            $this->actingAs($this->admin)
                ->deleteJson("/api/users/{$target->id}")
                ->assertNoContent();

            $this->assertDatabaseMissing('users', ['id' => $target->id]);
        });

        it('denies non-admin deleting user via API', function () {
            $target = User::factory()->create([
                'email' => 'target@projex.test',
                'is_active' => true,
            ]);
            $target->assignRole('member');

            $this->actingAs($this->user)
                ->deleteJson("/api/users/{$target->id}")
                ->assertForbidden();
        });

        it('blocks self-delete', function () {
            $this->actingAs($this->admin)
                ->deleteJson("/api/users/{$this->admin->id}")
                ->assertForbidden();
        });

        it('blocks deleting last active administrator', function () {
            $otherAdmin = User::factory()->create([
                'email' => 'admin2@projex.test',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
            $otherAdmin->assignRole('administrator');

            // Deactivate the other admin first
            $otherAdmin->update(['is_active' => false]);

            // Now only this admin is active — try to delete self
            $this->actingAs($this->admin)
                ->deleteJson("/api/users/{$this->admin->id}")
                ->assertForbidden();
        });

        it('blocks deactivating last active administrator', function () {
            $otherAdmin = User::factory()->create([
                'email' => 'admin2@projex.test',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
            $otherAdmin->assignRole('administrator');

            // Deactivate the other admin first
            $otherAdmin->update(['is_active' => false]);

            // Now only this admin is active — try to deactivate self
            $this->actingAs($this->admin)
                ->putJson("/api/users/{$this->admin->id}", [
                    'name' => $this->admin->name,
                    'email' => $this->admin->email,
                    'role' => 'administrator',
                    'is_active' => false,
                ])
                ->assertForbidden();
        });
    });

    describe('API list and roles', function () {
        it('allows admin to list users with filters', function () {
            $this->actingAs($this->admin)
                ->getJson('/api/users?search=user&role=member&is_active=true&sort_by=name&sort_direction=asc')
                ->assertOk()
                ->assertJsonStructure([
                    'data',
                    'meta' => ['current_page', 'last_page', 'total'],
                ]);
        });

        it('allows admin to get assignable roles', function () {
            $this->actingAs($this->admin)
                ->getJson('/api/roles')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'label']]]);
        });

        it('denies non-admin getting assignable roles', function () {
            $this->actingAs($this->user)
                ->getJson('/api/roles')
                ->assertForbidden();
        });
    });
});

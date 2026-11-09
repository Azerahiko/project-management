<?php

use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('User Management RBAC', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('administrator');

        $this->user = User::factory()->create([
            'email' => 'user@example.com',
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
                    'email' => 'new@example.com',
                    'temporary_password' => 'TemporaryPass123!',
                    'role' => 'member',
                ])
                ->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'name', 'email']]);

            $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        });

        it('rejects creating user with already existing invitation email', function () {
            Invitation::create([
                'name' => 'Existing Invite',
                'email' => 'invited@example.com',
                'role' => 'member',
                'token_hash' => hash('sha256', Str::random(64)),
                'expires_at' => now()->addHours(72),
                'invited_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)
                ->postJson('/api/users', [
                    'name' => 'Duplicate User',
                    'email' => 'invited@example.com',
                    'temporary_password' => 'TemporaryPass123!',
                    'role' => 'member',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('email');
        });

        it('cleans up invitation when deleting user', function () {
            $target = User::factory()->create([
                'email' => 'target-delete@example.com',
                'is_active' => true,
            ]);
            $target->assignRole('member');

            Invitation::create([
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'member',
                'token_hash' => hash('sha256', Str::random(64)),
                'expires_at' => now()->addHours(72),
                'invited_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)
                ->deleteJson("/api/users/{$target->id}")
                ->assertNoContent();

            $this->assertDatabaseMissing('users', ['id' => $target->id]);
            $this->assertDatabaseMissing('invitations', ['email' => $target->email]);
        });

        it('denies non-admin creating user via API', function () {
            $this->actingAs($this->user)
                ->postJson('/api/users', [
                    'name' => 'New User',
                    'email' => 'new@example.com',
                    'temporary_password' => 'TemporaryPass123!',
                    'role' => 'member',
                ])
                ->assertForbidden();
        });

        it('allows admin to update user via API', function () {
            $target = User::factory()->create([
                'email' => 'target@example.com',
                'is_active' => true,
            ]);
            $target->assignRole('member');

            $this->actingAs($this->admin)
                ->putJson("/api/users/{$target->id}", [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
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
                'email' => 'target@example.com',
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
                'email' => 'target@example.com',
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
                'email' => 'target@example.com',
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
                'email' => 'admin2@example.com',
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
                'email' => 'admin2@example.com',
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

        it('allows the last active administrator to update their own profile', function () {
            $this->actingAs($this->admin)
                ->putJson("/api/users/{$this->admin->id}", [
                    'name' => 'Updated Administrator',
                    'email' => 'updated-admin@example.com',
                    'role' => 'administrator',
                    'is_active' => true,
                ])
                ->assertOk()
                ->assertJsonPath('data.name', 'Updated Administrator');

            $this->assertDatabaseHas('users', [
                'id' => $this->admin->id,
                'name' => 'Updated Administrator',
                'is_active' => true,
            ]);
        });

        it('blocks demoting the last active administrator', function () {
            $this->actingAs($this->admin)
                ->putJson("/api/users/{$this->admin->id}", [
                    'name' => $this->admin->name,
                    'email' => $this->admin->email,
                    'role' => 'member',
                ])
                ->assertForbidden();

            expect($this->admin->fresh()->hasRole('administrator'))->toBeTrue();
        });

        it('allows demoting an administrator when another active administrator exists', function () {
            $otherAdmin = User::factory()->create([
                'email' => 'admin2@example.com',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
            $otherAdmin->assignRole('administrator');

            $this->actingAs($this->admin)
                ->putJson("/api/users/{$this->admin->id}", [
                    'name' => $this->admin->name,
                    'email' => $this->admin->email,
                    'role' => 'member',
                ])
                ->assertOk();

            expect($this->admin->fresh()->hasRole('administrator'))->toBeFalse();
            expect($this->admin->fresh()->hasRole('member'))->toBeTrue();
        });
    });

    describe('Temporary password', function () {
        it('generates a temporary password', function () {
            $response = $this->actingAs($this->admin)
                ->postJson('/api/users/generate-temporary-password')
                ->assertOk()
                ->assertJsonStructure(['temporary_password', 'message']);

            expect($response->json('temporary_password'))->toBeString();
        });

        it('generates a 20 character password with mixed character classes', function () {
            $password = $this->actingAs($this->admin)
                ->postJson('/api/users/generate-temporary-password')
                ->json('temporary_password');

            expect($password)->toHaveLength(20)
                ->and($password)->toMatch('/[A-Z]/')
                ->and($password)->toMatch('/[a-z]/')
                ->and($password)->toMatch('/[0-9]/')
                ->and($password)->toMatch('/[^A-Za-z0-9]/');
        });

        it('returns a different password on each regeneration', function () {
            $first = $this->actingAs($this->admin)
                ->postJson('/api/users/generate-temporary-password')
                ->json('temporary_password');

            $second = $this->actingAs($this->admin)
                ->postJson('/api/users/generate-temporary-password')
                ->json('temporary_password');

            expect($second)->not->toBe($first);
        });

        it('stores the submitted temporary password hashed and usable for login', function () {
            $password = 'TemporaryPass123!';

            $this->actingAs($this->admin)
                ->postJson('/api/users', [
                    'name' => 'Temp Password User',
                    'email' => 'temp-password@example.com',
                    'temporary_password' => $password,
                    'role' => 'member',
                ])
                ->assertCreated();

            $user = User::where('email', 'temp-password@example.com')->firstOrFail();

            expect($user->password)->not->toBe($password)
                ->and(Hash::check($password, $user->password))->toBeTrue();
        });

        it('does not create a user when generating a temporary password', function () {
            $before = User::count();

            $this->actingAs($this->admin)
                ->postJson('/api/users/generate-temporary-password')
                ->assertOk();

            expect(User::count())->toBe($before);
        });

        it('denies temporary password generation to non-admin users', function () {
            $this->actingAs($this->user)
                ->postJson('/api/users/generate-temporary-password')
                ->assertForbidden();
        });

        it('rate limits temporary password generation', function () {
            $this->actingAs($this->admin);

            for ($i = 0; $i < 60; $i++) {
                $this->postJson('/api/users/generate-temporary-password')->assertOk();
            }

            $this->postJson('/api/users/generate-temporary-password')->assertStatus(429);
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

            $this->actingAs($this->admin)
                ->getJson('/api/users?per_page=-5')
                ->assertOk()
                ->assertJsonPath('meta.per_page', 1);
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

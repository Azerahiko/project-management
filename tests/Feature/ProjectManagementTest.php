<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

describe('Project Management', function () {
    describe('Web UI Access', function () {
        it('allows admin to access projects index', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)->get('/projects')->assertOk();
        });

        it('allows project manager to access projects index', function () {
            [, $pm] = createTestUsers();
            $this->actingAs($pm)->get('/projects')->assertOk();
        });

        it('allows member to access projects index', function () {
            [, , $member] = createTestUsers();
            $this->actingAs($member)->get('/projects')->assertOk();
        });

        it('allows admin to access create project page', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)->get('/projects/create')->assertOk();
        });

        it('allows project manager to access create project page', function () {
            [, $pm] = createTestUsers();
            $this->actingAs($pm)->get('/projects/create')->assertOk();
        });

        it('denies member access to create project page', function () {
            [, , $member] = createTestUsers();
            $this->actingAs($member)->get('/projects/create')->assertForbidden();
        });

        it('allows project owner to access edit page', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)->get("/projects/{$project->id}/edit")->assertOk();
        });

        it('allows project manager (role) to access edit page', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($pm)->get("/projects/{$project->id}/edit")->assertOk();
        });

        it('denies member access to edit page', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)->get("/projects/{$project->id}/edit")->assertForbidden();
        });

        it('allows project member to view project detail', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)->get("/projects/{$project->id}")->assertOk();
        });

        it('denies access to project not member of', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)->get("/projects/{$otherProject->id}")->assertForbidden();
        });
    });

    describe('API CRUD', function () {
        it('allows admin to create project via API', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'New Project',
                    'description' => 'Project description',
                    'status' => 'active',
                    'priority' => 'high',
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->addMonth()->toDateString(),
                ])
                ->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'name', 'status', 'priority']]);

            $this->assertDatabaseHas('projects', ['name' => 'New Project']);
        });

        it('allows project manager to create project via API', function () {
            [, $pm] = createTestUsers();
            $this->actingAs($pm)
                ->postJson('/api/projects', [
                    'name' => 'PM Project',
                    'status' => 'draft',
                    'priority' => 'medium',
                ])
                ->assertCreated();

            $this->assertDatabaseHas('projects', ['name' => 'PM Project']);
        });

        it('denies member creating project via API', function () {
            [, , $member] = createTestUsers();
            $this->actingAs($member)
                ->postJson('/api/projects', [
                    'name' => 'Member Project',
                    'status' => 'draft',
                    'priority' => 'medium',
                ])
                ->assertForbidden();
        });

        it('allows admin to update project via API', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->putJson("/api/projects/{$project->id}", [
                    'name' => 'Updated Project Name',
                    'status' => 'completed',
                ])
                ->assertOk()
                ->assertJsonStructure(['data' => ['id', 'name', 'status']]);

            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'name' => 'Updated Project Name',
            ]);
        });

        it('allows project owner to update project via API', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, $pmProject] = createTestProjects($admin, $pm, $member);
            $this->actingAs($pm)
                ->putJson("/api/projects/{$pmProject->id}", [
                    'name' => 'Updated by PM',
                    'priority' => 'critical',
                ])
                ->assertOk();

            $this->assertDatabaseHas('projects', [
                'id' => $pmProject->id,
                'name' => 'Updated by PM',
            ]);
        });

        it('allows project member with manager role to update project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($pm)
                ->putJson("/api/projects/{$project->id}", [
                    'name' => 'Updated by Manager Role',
                ])
                ->assertOk();
        });

        it('denies regular member updating project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)
                ->putJson("/api/projects/{$project->id}", [
                    'name' => 'Hacked Name',
                ])
                ->assertForbidden();
        });

        it('allows admin to delete project via API', function () {
            [$admin] = createTestUsers();
            $target = Project::factory()->create(['owner_id' => $admin->id]);
            ProjectMember::create([
                'project_id' => $target->id,
                'user_id' => $admin->id,
                'role' => ProjectRole::OWNER,
            ]);

            $this->actingAs($admin)
                ->deleteJson("/api/projects/{$target->id}")
                ->assertNoContent();

            $this->assertSoftDeleted('projects', ['id' => $target->id]);
        });

        it('allows project owner to delete project via API', function () {
            [, $pm] = createTestUsers();
            $target = Project::factory()->create(['owner_id' => $pm->id]);
            ProjectMember::create([
                'project_id' => $target->id,
                'user_id' => $pm->id,
                'role' => ProjectRole::OWNER,
            ]);

            $this->actingAs($pm)
                ->deleteJson("/api/projects/{$target->id}")
                ->assertNoContent();

            $this->assertSoftDeleted('projects', ['id' => $target->id]);
        });

        it('denies project manager (role) deleting project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($pm)
                ->deleteJson("/api/projects/{$project->id}")
                ->assertForbidden();
        });

        it('denies member deleting project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)
                ->deleteJson("/api/projects/{$project->id}")
                ->assertForbidden();
        });
    });

    describe('API List and Filters', function () {
        it('allows admin to list projects with filters', function () {
            [$admin, $pm, $member] = createTestUsers();
            createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->getJson('/api/projects?search=Redesain&status=active&priority=high&sort_by=name&sort_direction=asc')
                ->assertOk()
                ->assertJsonStructure([
                    'data',
                    'meta' => ['current_page', 'last_page', 'total'],
                ]);
        });

        it('allows member to list accessible projects', function () {
            [$admin, $pm, $member] = createTestUsers();
            createTestProjects($admin, $pm, $member);
            $this->actingAs($member)
                ->getJson('/api/projects')
                ->assertOk()
                ->assertJsonStructure([
                    'data',
                    'meta' => ['current_page', 'last_page', 'total'],
                ]);

            // Member should only see projects they are part of
            $response = $this->actingAs($member)->getJson('/api/projects');
            $projectNames = collect($response->json('data'))->pluck('name')->toArray();
            $this->assertContains('Redesain Website Perusahaan', $projectNames);
            $this->assertContains('Sistem Manajemen Inventaris', $projectNames);
            // Should not see project they're not member of
            $this->assertNotContains('Aplikasi Mobile Internal', $projectNames);
        });

        it('returns pagination meta', function () {
            [$admin, $pm, $member] = createTestUsers();
            createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->getJson('/api/projects?per_page=2')
                ->assertOk()
                ->assertJsonStructure([
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ]);
        });

        it('allows getting project managers list', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->getJson('/api/projects/managers')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
        });
    });

    describe('Project Members API', function () {
        it('allows admin to list project members', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->getJson("/api/projects/{$project->id}/members")
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'user_id', 'role', 'user']]]);
        });

        it('allows project manager to list project members', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($pm)
                ->getJson("/api/projects/{$project->id}/members")
                ->assertOk();
        });

        it('allows member to list project members', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)
                ->getJson("/api/projects/{$project->id}/members")
                ->assertOk();
        });

        it('denies non-member listing project members', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)
                ->getJson("/api/projects/{$otherProject->id}/members")
                ->assertForbidden();
        });

        it('allows admin to add member to project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $newUser = User::factory()->create([
                'email' => 'newmember@projex.test',
                'password' => bcrypt('password'),
            ]);
            $newUser->assignRole('member');

            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'member',
                ])
                ->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'user_id', 'role', 'user']]);

            $this->assertDatabaseHas('project_members', [
                'project_id' => $project->id,
                'user_id' => $newUser->id,
                'role' => 'member',
            ]);
        });

        it('allows project manager to add member', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $newUser = User::factory()->create([
                'email' => 'newmember2@projex.test',
                'password' => bcrypt('password'),
            ]);
            $newUser->assignRole('member');

            $this->actingAs($pm)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'member',
                ])
                ->assertCreated();
        });

        it('denies regular member adding member', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $newUser = User::factory()->create([
                'email' => 'newmember3@projex.test',
                'password' => bcrypt('password'),
            ]);
            $newUser->assignRole('member');

            $this->actingAs($member)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'member',
                ])
                ->assertForbidden();
        });

        it('prevents adding duplicate member', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $member->id,
                    'role' => 'member',
                ])
                ->assertUnprocessable()
                ->assertJsonFragment(['message' => 'Pengguna sudah menjadi anggota proyek ini.']);
        });

        it('allows admin to update member role', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->putJson("/api/projects/{$project->id}/members/{$member->id}", [
                    'role' => 'manager',
                ])
                ->assertOk();

            $this->assertDatabaseHas('project_members', [
                'project_id' => $project->id,
                'user_id' => $member->id,
                'role' => 'manager',
            ]);
        });

        it('prevents changing owner role', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->putJson("/api/projects/{$project->id}/members/{$admin->id}", [
                    'role' => 'member',
                ])
                ->assertUnprocessable()
                ->assertJsonFragment(['message' => 'Tidak dapat mengubah peran pemilik proyek.']);
        });

        it('allows admin to remove member', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->deleteJson("/api/projects/{$project->id}/members/{$member->id}")
                ->assertNoContent();

            $this->assertDatabaseMissing('project_members', [
                'project_id' => $project->id,
                'user_id' => $member->id,
            ]);
        });

        it('prevents removing owner', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->deleteJson("/api/projects/{$project->id}/members/{$admin->id}")
                ->assertUnprocessable()
                ->assertJsonFragment(['message' => 'Tidak dapat menghapus pemilik proyek.']);
        });
    });

    describe('Policy Authorization', function () {
        it('allows admin to view any project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('view', $otherProject));
        });

        it('allows project owner to view project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('view', $project));
        });

        it('allows project member to view project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($member->can('view', $project));
        });

        it('denies non-member viewing project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->assertFalse($member->can('view', $otherProject));
        });

        it('allows admin to update any project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('update', $otherProject));
        });

        it('allows project owner to update project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('update', $project));
        });

        it('allows project manager role to update project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($pm->can('update', $project));
        });

        it('denies regular member updating project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertFalse($member->can('update', $project));
        });

        it('allows admin to delete any project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('delete', $otherProject));
        });

        it('allows project owner to delete project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('delete', $project));
        });

        it('denies project manager role deleting project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertFalse($pm->can('delete', $project));
        });

        it('allows admin to manage members of any project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('manageMembers', $otherProject));
        });

        it('allows project owner to manage members', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($admin->can('manageMembers', $project));
        });

        it('allows project manager role to manage members', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertTrue($pm->can('manageMembers', $project));
        });

        it('denies regular member managing members', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->assertFalse($member->can('manageMembers', $project));
        });
    });

    describe('Validation', function () {
        it('requires name for project creation', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'description' => 'No name',
                    'status' => 'draft',
                    'priority' => 'medium',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('name');
        });

        it('validates status enum', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'Test',
                    'status' => 'invalid_status',
                    'priority' => 'medium',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        });

        it('validates priority enum', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'Test',
                    'status' => 'draft',
                    'priority' => 'invalid_priority',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('priority');
        });

        it('validates end_date after start_date', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'Test',
                    'start_date' => now()->addDays(10)->toDateString(),
                    'end_date' => now()->toDateString(),
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('end_date');
        });

        it('validates member role enum', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $newUser = User::factory()->create(['email' => 'test@test.test']);
            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'invalid_role',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('role');
        });

        it('prevents adding owner as member', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $admin->id,
                    'role' => 'member',
                ])
                ->assertUnprocessable();
        });
    });
});

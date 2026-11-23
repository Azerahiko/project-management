<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
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

        it('allows developer to access projects index', function () {
            [, , $developer] = createTestUsers();
            $this->actingAs($developer)->get('/projects')->assertOk();
        });

        it('allows admin to access create project page', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)->get('/projects/create')->assertOk();
        });

        it('allows project manager to access create project page', function () {
            [, $pm] = createTestUsers();
            $this->actingAs($pm)->get('/projects/create')->assertOk();
        });

        it('denies developer access to create project page', function () {
            [, , $developer] = createTestUsers();
            $this->actingAs($developer)->get('/projects/create')->assertForbidden();
        });

        it('allows project owner to access edit page', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)->get("/projects/{$project->id}/edit")->assertOk();
        });

        it('allows project manager (role) to access edit page', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($pm)->get("/projects/{$project->id}/edit")->assertOk();
        });

        it('denies developer access to edit page', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)->get("/projects/{$project->id}/edit")->assertForbidden();
        });

        it('denies project managers access to edit pages for projects they cannot manage', function () {
            [$admin, $pm] = createTestUsers();
            $target = Project::factory()->create(['owner_id' => $admin->id]);
            ProjectMember::create([
                'project_id' => $target->id,
                'user_id' => $admin->id,
                'role' => ProjectRole::OWNER,
            ]);

            $this->actingAs($pm)->get("/projects/{$target->id}/edit")->assertForbidden();
        });

        it('allows project developer to view project detail', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)->get("/projects/{$project->id}")->assertOk();
        });

        it('denies access to project not developer of', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)->get("/projects/{$otherProject->id}")->assertForbidden();
        });
    });

    describe('API CRUD', function () {
        it('allows admin to create project via API', function () {
            [$admin, $client] = createTestUsers('client');
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'New Project',
                    'description' => 'Project description',
                    'status' => 'active',
                    'priority' => 'high',
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->addMonth()->toDateString(),
                    'client_ids' => [$client->id],
                ])
                ->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'name', 'status', 'priority']]);

            $this->assertDatabaseHas('projects', ['name' => 'New Project']);
        });

        it('allows project manager to create project via API', function () {
            [, $pm, $client] = createTestUsers(['client', 'pm']);
            $this->actingAs($pm)
                ->postJson('/api/projects', [
                    'name' => 'PM Project',
                    'status' => 'draft',
                    'priority' => 'medium',
                    'client_ids' => [$client->id],
                ])
                ->assertCreated();

            $this->assertDatabaseHas('projects', ['name' => 'PM Project']);
        });

        it('denies developer creating project via API', function () {
            [, , $developer] = createTestUsers();
            $this->actingAs($developer)
                ->postJson('/api/projects', [
                    'name' => 'Developer Project',
                    'status' => 'draft',
                    'priority' => 'medium',
                ])
                ->assertForbidden();
        });

        it('allows admin to update project via API', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
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
            [$admin, $pm, $developer] = createTestUsers();
            [, $pmProject] = createTestProjects($admin, $pm, $developer);
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

        it('allows project developer with manager role to update project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($pm)
                ->putJson("/api/projects/{$project->id}", [
                    'name' => 'Updated by Manager Role',
                ])
                ->assertOk();
        });

        it('denies regular developer updating project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)
                ->putJson("/api/projects/{$project->id}", [
                    'name' => 'Hacked Name',
                ])
                ->assertForbidden();
        });

        it('syncs project ownership when the owner changes', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);

            $this->actingAs($admin)
                ->putJson("/api/projects/{$project->id}", [
                    'owner_id' => $pm->id,
                ])
                ->assertOk()
                ->assertJsonPath('data.owner_id', $pm->id);

            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'owner_id' => $pm->id,
            ]);
            $this->assertDatabaseHas('project_members', [
                'project_id' => $project->id,
                'user_id' => $pm->id,
                'role' => 'owner',
            ]);
            $this->assertDatabaseHas('project_members', [
                'project_id' => $project->id,
                'user_id' => $admin->id,
                'role' => 'manager',
            ]);
        });

        it('rejects transferring ownership to an inactive user', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $inactive = User::factory()->create([
                'email' => 'inactive@example.com',
                'is_active' => false,
            ]);
            $inactive->assignRole('project_manager');

            $this->actingAs($admin)
                ->putJson("/api/projects/{$project->id}", [
                    'owner_id' => $inactive->id,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('owner_id');

            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'owner_id' => $admin->id,
            ]);
        });

        it('denies project managers from transferring project ownership', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);

            $this->actingAs($pm)
                ->putJson("/api/projects/{$project->id}", [
                    'owner_id' => $pm->id,
                ])
                ->assertForbidden();

            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'owner_id' => $admin->id,
            ]);
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
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($pm)
                ->deleteJson("/api/projects/{$project->id}")
                ->assertForbidden();
        });

        it('denies developer deleting project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)
                ->deleteJson("/api/projects/{$project->id}")
                ->assertForbidden();
        });
    });

    describe('API List and Filters', function () {
        it('allows admin to list projects with filters', function () {
            [$admin, $pm, $developer] = createTestUsers();
            createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->getJson('/api/projects?search=Redesain&status=active&priority=high&sort_by=name&sort_direction=asc')
                ->assertOk()
                ->assertJsonStructure([
                    'data',
                    'meta' => ['current_page', 'last_page', 'total'],
                ]);
        });

        it('allows developer to list accessible projects', function () {
            [$admin, $pm, $developer] = createTestUsers();
            createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)
                ->getJson('/api/projects')
                ->assertOk()
                ->assertJsonStructure([
                    'data',
                    'meta' => ['current_page', 'last_page', 'total'],
                ]);

            // Developer should only see projects they are part of
            $response = $this->actingAs($developer)->getJson('/api/projects');
            $projectNames = collect($response->json('data'))->pluck('name')->toArray();
            $this->assertContains('Redesain Website Perusahaan', $projectNames);
            $this->assertContains('Sistem Manajemen Inventaris', $projectNames);
            // Should not see project they're not developer of
            $this->assertNotContains('Aplikasi Mobile Internal', $projectNames);
        });

        it('returns pagination meta', function () {
            [$admin, $pm, $developer] = createTestUsers();
            createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->getJson('/api/projects?per_page=2')
                ->assertOk()
                ->assertJsonStructure([
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ]);

            $this->actingAs($admin)
                ->getJson('/api/projects?per_page=-5')
                ->assertOk()
                ->assertJsonPath('meta.per_page', 1);
        });

        it('returns accurate developer counts in the project listing', function () {
            [$admin, $pm, $developer] = createTestUsers();
            createTestProjects($admin, $pm, $developer);

            $response = $this->actingAs($admin)
                ->getJson('/api/projects?search=Redesain')
                ->assertOk();

            $project = collect($response->json('data'))->firstWhere('name', 'Redesain Website Perusahaan');

            expect($project)->not->toBeNull();
            expect($project['members_count'])->toBe(3);
        });

        it('allows getting project managers list', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->getJson('/api/projects/managers')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
        });

        it('allows getting project clients list', function () {
            [$admin] = createTestUsers();
            $client = User::factory()->create(['is_active' => true]);
            $client->assignRole('client');

            $this->actingAs($admin)
                ->getJson('/api/projects/clients')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'email']]])
                ->assertJsonFragment(['id' => $client->id, 'name' => $client->name]);
        });

        it('includes team leaders in the project managers list', function () {
            [$admin] = createTestUsers();

            $teamLeader = User::factory()->create(['is_active' => true]);
            $teamLeader->assignRole('team_leader');

            $response = $this->actingAs($admin)
                ->getJson('/api/projects/managers')
                ->assertOk();

            expect(collect($response->json('data'))->pluck('id'))->toContain($teamLeader->id);
        });

        it('includes project-level managers when a project is given', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);

            $projectManager = User::factory()->create(['is_active' => true]);
            $projectManager->assignRole('developer');
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $projectManager->id,
                'role' => ProjectRole::MANAGER,
            ]);

            $response = $this->actingAs($admin)
                ->getJson("/api/projects/managers?project={$project->id}")
                ->assertOk();

            expect(collect($response->json('data'))->pluck('id'))->toContain($projectManager->id);
        });

        it('excludes managers from other projects when a project is given', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);

            $otherProject = Project::create([
                'name' => 'Proyek Lain',
                'description' => 'Other project',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::LOW,
                'start_date' => now(),
                'end_date' => now()->addWeek(),
                'owner_id' => $admin->id,
            ]);

            $otherManager = User::factory()->create(['is_active' => true]);
            $otherManager->assignRole('developer');
            ProjectMember::create([
                'project_id' => $otherProject->id,
                'user_id' => $otherManager->id,
                'role' => ProjectRole::MANAGER,
            ]);

            $response = $this->actingAs($admin)
                ->getJson("/api/projects/managers?project={$project->id}")
                ->assertOk();

            expect(collect($response->json('data'))->pluck('id'))->not->toContain($otherManager->id);
        });
    });

    describe('Project Developers API', function () {
        it('allows admin to list project members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->getJson("/api/projects/{$project->id}/members")
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'user_id', 'role', 'user']]]);
        });

        it('allows project manager to list project members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($pm)
                ->getJson("/api/projects/{$project->id}/members")
                ->assertOk();
        });

        it('allows developer to list project members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)
                ->getJson("/api/projects/{$project->id}/members")
                ->assertOk();
        });

        it('denies non-developer listing project members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($developer)
                ->getJson("/api/projects/{$otherProject->id}/members")
                ->assertForbidden();
        });

        it('lists only active users who are not already project members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $candidate = User::factory()->create([
                'email' => 'candidate@example.com',
                'is_active' => true,
            ]);
            $candidate->assignRole('developer');
            $inactive = User::factory()->create([
                'email' => 'inactive@example.com',
                'is_active' => false,
            ]);
            $inactive->assignRole('developer');

            $response = $this->actingAs($pm)
                ->getJson("/api/projects/{$project->id}/available-users")
                ->assertOk();

            $ids = collect($response->json('data'))->pluck('id');

            expect($ids)->toContain($candidate->id);
            expect($ids)->not->toContain($admin->id);
            expect($ids)->not->toContain($pm->id);
            expect($ids)->not->toContain($developer->id);
            expect($ids)->not->toContain($inactive->id);
        });

        it('denies regular members from listing available users', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);

            $this->actingAs($developer)
                ->getJson("/api/projects/{$project->id}/available-users")
                ->assertForbidden();
        });

        it('allows admin to add developer to project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $newUser = User::factory()->create([
                'email' => 'newmember@example.com',
                'password' => bcrypt('password'),
            ]);
            $newUser->assignRole('developer');

            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'developer',
                ])
                ->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'user_id', 'role', 'user']]);

            $this->assertDatabaseHas('project_members', [
                'project_id' => $project->id,
                'user_id' => $newUser->id,
                'role' => 'developer',
            ]);
        });

        it('allows project manager to add developer', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $newUser = User::factory()->create([
                'email' => 'newmember2@example.com',
                'password' => bcrypt('password'),
            ]);
            $newUser->assignRole('developer');

            $this->actingAs($pm)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'developer',
                ])
                ->assertCreated();
        });

        it('denies regular developer adding developer', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $newUser = User::factory()->create([
                'email' => 'newmember3@example.com',
                'password' => bcrypt('password'),
            ]);
            $newUser->assignRole('developer');

            $this->actingAs($developer)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'developer',
                ])
                ->assertForbidden();
        });

        it('prevents adding duplicate developer', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $developer->id,
                    'role' => 'developer',
                ])
                ->assertUnprocessable()
                ->assertJsonFragment(['message' => 'Pengguna sudah menjadi anggota proyek ini.']);
        });

        it('allows admin to update developer role', function () {
            [$admin, $pm, $developer] = createTestUsers();
            $project = Project::create([
                'name' => 'Test Project',
                'description' => 'Test project',
                'status' => 'active',
                'priority' => 'high',
                'owner_id' => $admin->id,
            ]);

            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $admin->id,
                'role' => 'owner',
            ]);

            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $pm->id,
                'role' => 'developer',
            ]);

            $this->actingAs($admin)
                ->putJson("/api/projects/{$project->id}/members/{$pm->id}", [
                    'role' => 'manager',
                ])
                ->assertOk();

            $this->assertDatabaseHas('project_members', [
                'project_id' => $project->id,
                'user_id' => $pm->id,
                'role' => 'manager',
            ]);
        });

        it('prevents changing owner role', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->putJson("/api/projects/{$project->id}/members/{$admin->id}", [
                    'role' => 'developer',
                ])
                ->assertUnprocessable()
                ->assertJsonFragment(['message' => 'Tidak dapat mengubah peran pemilik proyek.']);
        });

        it('allows admin to remove developer', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->deleteJson("/api/projects/{$project->id}/members/{$developer->id}")
                ->assertNoContent();

            $this->assertDatabaseMissing('project_members', [
                'project_id' => $project->id,
                'user_id' => $developer->id,
            ]);
        });

        it('prevents removing owner', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->deleteJson("/api/projects/{$project->id}/members/{$admin->id}")
                ->assertUnprocessable()
                ->assertJsonFragment(['message' => 'Tidak dapat menghapus pemilik proyek.']);
        });
    });

    describe('Policy Authorization', function () {
        it('allows admin to view any project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('view', $otherProject));
        });

        it('allows project owner to view project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('view', $project));
        });

        it('allows project developer to view project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($developer->can('view', $project));
        });

        it('denies non-developer viewing project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $developer);
            $this->assertFalse($developer->can('view', $otherProject));
        });

        it('allows admin to update any project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('update', $otherProject));
        });

        it('allows project owner to update project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('update', $project));
        });

        it('allows project manager role to update project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($pm->can('update', $project));
        });

        it('denies regular developer updating project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertFalse($developer->can('update', $project));
        });

        it('allows admin to delete any project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('delete', $otherProject));
        });

        it('allows project owner to delete project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('delete', $project));
        });

        it('denies project manager role deleting project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertFalse($pm->can('delete', $project));
        });

        it('allows admin to manage members of any project', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [, , $otherProject] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('manageMembers', $otherProject));
        });

        it('allows project owner to manage members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($admin->can('manageMembers', $project));
        });

        it('allows project manager role to manage members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertTrue($pm->can('manageMembers', $project));
        });

        it('denies regular developer managing members', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->assertFalse($developer->can('manageMembers', $project));
        });
    });

    describe('Validation', function () {
        it('requires name for project creation', function () {
            [$admin, $client] = createTestUsers('client');
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => '',  // empty name to trigger validation error
                    'description' => 'No name',
                    'status' => 'draft',
                    'priority' => 'medium',
                    'client_ids' => [$client->id],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('name');
        });

        it('validates status enum', function () {
            [$admin, $client] = createTestUsers('client');
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'Test',
                    'status' => 'invalid_status',
                    'priority' => 'medium',
                    'client_ids' => [$client->id],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        });

        it('validates priority enum', function () {
            [$admin, $client] = createTestUsers('client');
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'Test',
                    'status' => 'draft',
                    'priority' => 'invalid_priority',
                    'client_ids' => [$client->id],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('priority');
        });

        it('validates end_date after start_date', function () {
            [$admin, $client] = createTestUsers('client');
            $this->actingAs($admin)
                ->postJson('/api/projects', [
                    'name' => 'Test',
                    'start_date' => now()->addDays(10)->toDateString(),
                    'end_date' => now()->toDateString(),
                    'client_ids' => [$client->id],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('end_date');
        });

        it('validates developer role enum', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $newUser = User::factory()->create(['email' => 'test@test.test']);
            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $newUser->id,
                    'role' => 'invalid_role',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('role');
        });

        it('prevents adding owner as developer', function () {
            [$admin, $pm, $developer] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $developer);
            $this->actingAs($admin)
                ->postJson("/api/projects/{$project->id}/members", [
                    'user_id' => $admin->id,
                    'role' => 'developer',
                ])
                ->assertUnprocessable();
        });
    });
});

describe('Ownership transfer notifications (H-4)', function () {
    it('notifies previous and new owners with correct payload and project URL', function () {
        [$admin, $pm, $developer] = createTestUsers();
        [$project] = createTestProjects($admin, $pm, $developer);

        $this->actingAs($admin)
            ->putJson("/api/projects/{$project->id}", [
                'owner_id' => $pm->id,
            ])
            ->assertOk();

        // Previous owner (admin) should receive notification
        $adminNotifications = $admin->fresh()->notifications;
        expect($adminNotifications)->toHaveCount(1);
        $adminNotif = $adminNotifications->first();
        expect($adminNotif->data['action'])->toBe('ownership_transferred');
        expect($adminNotif->data['project_id'])->toBe($project->id);
        expect($adminNotif->data['previous_owner_id'])->toBe($admin->id);
        expect($adminNotif->data['new_owner_id'])->toBe($pm->id);
        expect($adminNotif->data['role'])->toBe('previous_owner');
        expect($adminNotif->data['url'])->toBe(route('projects.show', $project->id, false));

        // New owner (pm) should receive notification
        $pmNotifications = $pm->fresh()->notifications;
        expect($pmNotifications)->toHaveCount(1);
        $pmNotif = $pmNotifications->first();
        expect($pmNotif->data['action'])->toBe('ownership_transferred');
        expect($pmNotif->data['project_id'])->toBe($project->id);
        expect($pmNotif->data['previous_owner_id'])->toBe($admin->id);
        expect($pmNotif->data['new_owner_id'])->toBe($pm->id);
        expect($pmNotif->data['role'])->toBe('new_owner');
        expect($pmNotif->data['url'])->toBe(route('projects.show', $project->id, false));
    });

    it('uses idempotency key with project and owner ids', function () {
        [$admin, $pm, $developer] = createTestUsers();
        [$project] = createTestProjects($admin, $pm, $developer);

        // Transfer twice
        $this->actingAs($admin)->putJson("/api/projects/{$project->id}", ['owner_id' => $pm->id])->assertOk();
        $this->actingAs($admin)->putJson("/api/projects/{$project->id}", ['owner_id' => $admin->id])->assertOk();

        // Should only create 2 notifications per user (one per transfer)
        $adminNotifs = $admin->fresh()->notifications;
        $pmNotifs = $pm->fresh()->notifications;
        expect($adminNotifs)->toHaveCount(2);
        expect($pmNotifs)->toHaveCount(2);
    });
});

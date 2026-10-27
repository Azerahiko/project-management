<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;

describe('Task Management', function () {
    describe('Web UI Access', function () {
        it('allows admin to access tasks index', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)->get('/tasks')->assertOk();
        });

        it('allows project manager to access tasks index', function () {
            [, $pm] = createTestUsers();
            $this->actingAs($pm)->get('/tasks')->assertOk();
        });

        it('allows member to access tasks index', function () {
            [, , $member] = createTestUsers();
            $this->actingAs($member)->get('/tasks')->assertOk();
        });

        it('allows admin to access create task page', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)->get('/tasks/create')->assertOk();
        });

        it('allows project manager to access create task page', function () {
            [, $pm] = createTestUsers();
            $this->actingAs($pm)->get('/tasks/create')->assertOk();
        });

        it('allows member to access create task page', function () {
            [, , $member] = createTestUsers();
            $this->actingAs($member)->get('/tasks/create')->assertOk();
        });

        it('allows project member to view task detail', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($member)->get("/tasks/{$task1->id}")->assertOk();
        });

        it('allows task creator to view task detail', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)->get("/tasks/{$task1->id}")->assertOk();
        });

        it('allows task assignee to view task detail', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($pm)->get("/tasks/{$task1->id}")->assertOk();
        });

        it('denies access to task not member of project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, , $otherProject] = createTestProjects($admin, $pm, $member);
            [, , , $task4] = createTestTasks($admin, $pm, $member, $project, $project, $otherProject);
            $this->actingAs($member)->get("/tasks/{$task4->id}")->assertForbidden();
        });

        it('allows task owner to access edit page', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)->get("/tasks/{$task1->id}/edit")->assertOk();
        });

        it('allows task assignee to access edit page', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($member)->get("/tasks/{$task1->id}/edit")->assertOk();
        });

        it('allows project manager role to access edit page', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [, $task2] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($pm)->get("/tasks/{$task2->id}/edit")->assertOk();
        });

        it('denies regular member (not creator/assignee/manager) editing task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [, $task2] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($member)->get("/tasks/{$task2->id}/edit")->assertForbidden();
        });
    });

    describe('API CRUD', function () {
        it('allows admin to create task via API', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'New Task',
                    'description' => 'Task description',
                    'status' => 'todo',
                    'priority' => 'high',
                    'assignee_id' => $member->id,
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addWeek()->toDateString(),
                ])
                ->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'title', 'status', 'priority']]);

            $this->assertDatabaseHas('tasks', ['title' => 'New Task']);
        });

        it('allows project manager to create task via API', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($pm)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'PM Task',
                    'status' => 'todo',
                    'priority' => 'medium',
                ])
                ->assertCreated();

            $this->assertDatabaseHas('tasks', ['title' => 'PM Task']);
        });

        it('allows member to create task via API', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'Member Task',
                    'status' => 'todo',
                    'priority' => 'medium',
                ])
                ->assertCreated();
        });

        it('denies creating task for project not member of', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, , $otherProject] = createTestProjects($admin, $pm, $member);
            $this->actingAs($member)
                ->postJson('/api/tasks', [
                    'project_id' => $otherProject->id,
                    'title' => 'Unauthorized Task',
                    'status' => 'todo',
                    'priority' => 'medium',
                ])
                ->assertForbidden();
        });

        it('requires title for task creation', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'description' => 'No title',
                    'status' => 'todo',
                    'priority' => 'medium',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('title');
        });

        it('requires project_id for task creation', function () {
            [$admin] = createTestUsers();
            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'title' => 'No Project',
                    'status' => 'todo',
                    'priority' => 'medium',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('project_id');
        });

        it('validates status enum', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'Test',
                    'status' => 'invalid_status',
                    'priority' => 'medium',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        });

        it('validates priority enum', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'Test',
                    'status' => 'todo',
                    'priority' => 'invalid_priority',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('priority');
        });

        it('validates due_date after start_date', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'Test',
                    'start_date' => now()->addDays(10)->toDateString(),
                    'due_date' => now()->toDateString(),
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('due_date');
        });

        it('prevents assignee not member of project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $outsider = User::factory()->create(['email' => 'outsider@test.com']);
            $outsider->assignRole('member');

            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'Test',
                    'assignee_id' => $outsider->id,
                    'status' => 'todo',
                    'priority' => 'medium',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('assignee_id');
        });

        it('returns a validation error for a non-existent assignee instead of crashing', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);

            $this->actingAs($admin)
                ->postJson('/api/tasks', [
                    'project_id' => $project->id,
                    'title' => 'Test',
                    'assignee_id' => 999999,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('assignee_id');
        });

        it('returns a validation error when updating with a non-existent assignee', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task] = createTestTasks($admin, $pm, $member, $project, $project, $project);

            $this->actingAs($admin)
                ->putJson("/api/tasks/{$task->id}", [
                    'assignee_id' => 999999,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('assignee_id');
        });

        it('allows admin to update task via API', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->putJson("/api/tasks/{$task1->id}", [
                    'title' => 'Updated Task Title',
                    'status' => 'done',
                ])
                ->assertOk()
                ->assertJsonStructure(['data' => ['id', 'title', 'status']]);

            $this->assertDatabaseHas('tasks', [
                'id' => $task1->id,
                'title' => 'Updated Task Title',
                'status' => 'done',
            ]);
        });

        it('allows task creator to update task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->putJson("/api/tasks/{$task1->id}", [
                    'priority' => 'urgent',
                ])
                ->assertOk();

            $this->assertDatabaseHas('tasks', [
                'id' => $task1->id,
                'priority' => 'urgent',
            ]);
        });

        it('allows task assignee to update task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($member)
                ->putJson("/api/tasks/{$task1->id}", [
                    'status' => 'in_progress',
                ])
                ->assertOk();
        });

        it('allows project manager role to update task in project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [, $task2] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($pm)
                ->putJson("/api/tasks/{$task2->id}", [
                    'title' => 'Updated by Manager Role',
                ])
                ->assertOk();
        });

        it('denies regular member (not creator/assignee/manager) updating task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [, $task2] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($member)
                ->putJson("/api/tasks/{$task2->id}", [
                    'title' => 'Hacked Title',
                ])
                ->assertForbidden();
        });

        it('allows admin to delete task via API', function () {
            [$admin] = createTestUsers();
            $project = Project::factory()->create(['owner_id' => $admin->id]);
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $admin->id,
                'role' => ProjectRole::OWNER,
            ]);
            $task = Task::factory()->create(['project_id' => $project->id, 'creator_id' => $admin->id]);

            $this->actingAs($admin)
                ->deleteJson("/api/tasks/{$task->id}")
                ->assertNoContent();

            $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        });

        it('allows task creator to delete task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->deleteJson("/api/tasks/{$task1->id}")
                ->assertNoContent();

            $this->assertSoftDeleted('tasks', ['id' => $task1->id]);
        });

        it('allows project owner to delete task in project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->deleteJson("/api/tasks/{$task1->id}")
                ->assertNoContent();
        });

        it('denies project manager role deleting task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($pm)
                ->deleteJson("/api/tasks/{$task1->id}")
                ->assertForbidden();
        });

        it('denies member deleting task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($member)
                ->deleteJson("/api/tasks/{$task1->id}")
                ->assertForbidden();
        });

        it('sets completed_at when status changed to done', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->putJson("/api/tasks/{$task1->id}", [
                    'status' => 'done',
                ])
                ->assertOk();

            $task1->refresh();
            $this->assertNotNull($task1->completed_at);
            $this->assertEquals('done', $task1->status->value);
        });

        it('clears completed_at when status changed from done', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            // First set to done
            $task1->update(['status' => TaskStatus::DONE, 'completed_at' => now()]);
            $this->actingAs($admin)
                ->putJson("/api/tasks/{$task1->id}", [
                    'status' => 'in_progress',
                ])
                ->assertOk();

            $task1->refresh();
            $this->assertNull($task1->completed_at);
            $this->assertEquals('in_progress', $task1->status->value);
        });
    });

    describe('API List and Filters', function () {
        it('allows admin to list tasks with filters', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->getJson('/api/tasks?search=Design&status=todo&priority=high&sort_by=title&sort_direction=asc')
                ->assertOk()
                ->assertJsonStructure([
                    'data',
                    'meta' => ['current_page', 'last_page', 'total'],
                ]);
        });

        it('allows member to list accessible tasks', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, , $otherProject] = createTestProjects($admin, $pm, $member);
            createTestTasks($admin, $pm, $member, $project, $project, $otherProject);
            $this->actingAs($member)
                ->getJson('/api/tasks')
                ->assertOk()
                ->assertJsonStructure([
                    'data',
                    'meta' => ['current_page', 'last_page', 'total'],
                ]);

            // Member should only see tasks from projects they are part of
            $response = $this->actingAs($member)->getJson('/api/tasks');
            $taskTitles = collect($response->json('data'))->pluck('title')->toArray();
            $this->assertContains('Design Homepage', $taskTitles);
            $this->assertContains('Implement API', $taskTitles);
            $this->assertNotContains('Mobile App Design', $taskTitles);
        });

        it('returns pagination meta', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->getJson('/api/tasks?per_page=2')
                ->assertOk()
                ->assertJsonStructure([
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ]);

            $this->actingAs($admin)
                ->getJson('/api/tasks?per_page=-5')
                ->assertOk()
                ->assertJsonPath('meta.per_page', 1);
        });

        it('filters tasks by assignee', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->actingAs($admin)
                ->getJson("/api/tasks?assignee_id={$member->id}")
                ->assertOk();
            $response = $this->actingAs($admin)->getJson("/api/tasks?assignee_id={$member->id}");
            $assigneeIds = collect($response->json('data'))->pluck('assignee_id')->unique()->toArray();
            $this->assertEquals([$member->id], $assigneeIds);
        });

        it('filters tasks by project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, $pmProject] = createTestProjects($admin, $pm, $member);
            createTestTasks($admin, $pm, $member, $project, $pmProject, $pmProject);
            $this->actingAs($admin)
                ->getJson("/api/tasks?project_id={$project->id}")
                ->assertOk();
            $response = $this->actingAs($admin)->getJson("/api/tasks?project_id={$project->id}");
            $projectIds = collect($response->json('data'))->pluck('project_id')->unique()->toArray();
            $this->assertEquals([$project->id], $projectIds);
        });

        it('returns assignees for project', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            $this->actingAs($admin)
                ->getJson("/api/projects/{$project->id}/assignees")
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
        });
    });

    describe('Policy Authorization', function () {
        it('allows admin to view any task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, , $otherProject] = createTestProjects($admin, $pm, $member);
            [, , , $task4] = createTestTasks($admin, $pm, $member, $project, $project, $otherProject);
            $this->assertTrue($admin->can('view', $task4));
        });

        it('allows task creator to view task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($admin->can('view', $task1));
        });

        it('allows task assignee to view task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($member->can('view', $task1));
        });

        it('allows project member to view task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [, $task2] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($member->can('view', $task2));
        });

        it('denies non-member viewing task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, , $otherProject] = createTestProjects($admin, $pm, $member);
            [, , , $task4] = createTestTasks($admin, $pm, $member, $project, $project, $otherProject);
            $this->assertFalse($member->can('view', $task4));
        });

        it('allows admin to update any task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, , $otherProject] = createTestProjects($admin, $pm, $member);
            [, , , $task4] = createTestTasks($admin, $pm, $member, $project, $project, $otherProject);
            $this->assertTrue($admin->can('update', $task4));
        });

        it('allows task creator to update task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($admin->can('update', $task1));
        });

        it('allows task assignee to update task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($member->can('update', $task1));
        });

        it('allows project manager role to update task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [, $task2] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($pm->can('update', $task2));
        });

        it('denies regular member (not creator/assignee/manager) updating task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [, $task2] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertFalse($member->can('update', $task2));
        });

        it('allows admin to delete any task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, , $otherProject] = createTestProjects($admin, $pm, $member);
            [, , , $task4] = createTestTasks($admin, $pm, $member, $project, $project, $otherProject);
            $this->assertTrue($admin->can('delete', $task4));
        });

        it('allows task creator to delete task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($admin->can('delete', $task1));
        });

        it('allows project owner to delete task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertTrue($admin->can('delete', $task1));
        });

        it('denies project manager role deleting task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertFalse($pm->can('delete', $task1));
        });

        it('denies member deleting task', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);
            [$task1] = createTestTasks($admin, $pm, $member, $project, $project, $project);
            $this->assertFalse($member->can('delete', $task1));
        });
    });

    describe('Model Helpers', function () {
        it('returns correct status label', function () {
            $task = Task::factory()->create(['status' => TaskStatus::TODO]);
            $this->assertEquals('To Do', $task->status_label);

            $task->status = TaskStatus::IN_PROGRESS;
            $this->assertEquals('Sedang Dikerjakan', $task->status_label);

            $task->status = TaskStatus::REVIEW;
            $this->assertEquals('Review', $task->status_label);

            $task->status = TaskStatus::DONE;
            $this->assertEquals('Selesai', $task->status_label);
        });

        it('returns correct priority label', function () {
            $task = Task::factory()->create(['priority' => TaskPriority::LOW]);
            $this->assertEquals('Rendah', $task->priority_label);

            $task->priority = TaskPriority::MEDIUM;
            $this->assertEquals('Sedang', $task->priority_label);

            $task->priority = TaskPriority::HIGH;
            $this->assertEquals('Tinggi', $task->priority_label);

            $task->priority = TaskPriority::URGENT;
            $this->assertEquals('Mendesak', $task->priority_label);
        });

        it('checks overdue correctly', function () {
            $task = Task::factory()->create([
                'due_date' => now()->subDay(),
                'status' => TaskStatus::TODO,
            ]);
            $this->assertTrue($task->isOverdue());

            $task->status = TaskStatus::DONE;
            $this->assertFalse($task->isOverdue());

            $task->status = TaskStatus::TODO;
            $task->due_date = now()->addDay();
            $this->assertFalse($task->isOverdue());

            $task->due_date = null;
            $this->assertFalse($task->isOverdue());
        });

        it('marks task completed correctly', function () {
            $task = Task::factory()->create(['status' => TaskStatus::TODO]);
            $this->assertNull($task->completed_at);
            $task->markCompleted();
            $this->assertEquals('done', $task->status->value);
            $this->assertNotNull($task->completed_at);
        });
    });
});

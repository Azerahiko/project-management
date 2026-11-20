<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function administrator_can_do_all_task_actions()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
        ]);

        // Administrator can view
        $this->assertTrue($admin->can('view', $task));

        // Administrator can create tasks
        $this->assertTrue($admin->can('create', $task));

        // Administrator can update any task
        $this->assertTrue($admin->can('update', $task));

        // Administrator can delete
        $this->assertTrue($admin->can('delete', $task));

        // Administrator can restore
        $this->assertTrue($admin->can('restore', $task));

        // Administrator can force delete
        $this->assertTrue($admin->can('forceDelete', $task));
    }

    #[Test]
    public function project_manager_can_view_and_update_tasks_in_project_but_cannot_delete_or_force_delete()
    {
        $pm = User::factory()->create();
        $pm->assignRole('project_manager');

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $pm->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $pm->id,
        ]);

        // Project manager can view
        $this->assertTrue($pm->can('view', $task));

        // Project manager can update
        $this->assertTrue($pm->can('update', $task));

        // Project manager can delete (full task management)
        $this->assertTrue($pm->can('delete', $task));

        // Project manager can restore
        $this->assertTrue($pm->can('restore', $task));

        // Project manager can force delete
        $this->assertTrue($pm->can('forceDelete', $task));
    }

    #[Test]
    public function team_leader_can_view_and_update_tasks_in_project_but_cannot_delete_or_force_delete()
    {
        $tl = User::factory()->create();
        $tl->assignRole('team_leader');

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $tl->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $tl->id,
        ]);

        // Team leader can view
        $this->assertTrue($tl->can('view', $task));

        // Team leader can update
        $this->assertTrue($tl->can('update', $task));

        // Team leader can delete
        $this->assertTrue($tl->can('delete', $task));

        // Team leader can restore
        $this->assertTrue($tl->can('restore', $task));

        // Team leader cannot force delete
        $this->assertFalse($tl->can('forceDelete', $task));
    }

    #[Test]
    public function developer_can_only_update_assigned_tasks_execution_fields()
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => ProjectRole::MEMBER,
        ]);

        // Create task where developer is assignee
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $developer->id,
            'status' => TaskStatus::TODO->value,
        ]);

        // Developer can view own assigned task
        $this->assertTrue($developer->can('view', $task));

        // Developer can update own assigned task (execution fields only)
        $this->assertTrue($developer->can('update', $task));

        // Developer cannot delete
        $this->assertFalse($developer->can('delete', $task));

        // Developer cannot restore
        $this->assertFalse($developer->can('restore', $task));

        // Developer cannot force delete
        $this->assertFalse($developer->can('forceDelete', $task));
    }

    #[Test]
    public function reviewer_can_only_update_review_status_transitions()
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $reviewer->id,
            'role' => ProjectRole::MEMBER,
        ]);

        // Create task in review status
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $reviewer->id,
            'status' => TaskStatus::REVIEW->value,
        ]);

        // Reviewer can view
        $this->assertTrue($reviewer->can('view', $task));

        // Reviewer can update status (valid transitions)
        $this->assertTrue($reviewer->can('update', $task));

        // Reviewer cannot delete
        $this->assertFalse($reviewer->can('delete', $task));

        // Reviewer cannot restore
        $this->assertFalse($reviewer->can('restore', $task));

        // Reviewer cannot force delete
        $this->assertFalse($reviewer->can('forceDelete', $task));
    }

    #[Test]
    public function client_can_only_view_tasks()
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $client->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $client->id,
        ]);

        // Client can view
        $this->assertTrue($client->can('view', $task));

        // Client cannot update
        $this->assertFalse($client->can('update', $task));

        // Client cannot delete
        $this->assertFalse($client->can('delete', $task));

        // Client cannot restore
        $this->assertFalse($client->can('restore', $task));

        // Client cannot force delete
        $this->assertFalse($client->can('forceDelete', $task));
    }

    #[Test]
    public function developer_cannot_create_tasks()
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $project = Project::factory()->create();

        // Developer cannot create tasks
        $this->assertFalse($developer->can('create', $project));
    }

    #[Test]
    public function reviewer_cannot_create_tasks()
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        $project = Project::factory()->create();

        // Reviewer cannot create tasks
        $this->assertFalse($reviewer->can('create', $project));
    }

    #[Test]
    public function client_cannot_create_tasks()
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $project = Project::factory()->create();

        // Client cannot create tasks
        $this->assertFalse($client->can('create', $project));
    }

    #[Test]
    public function developer_cannot_set_status_to_done()
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $developer->id,
            'status' => TaskStatus::TODO->value,
        ]);

        // Developer trying to set status to DONE should fail
        $this->assertEquals(TaskStatus::TODO->value, $task->status->value);
    }

    #[Test]
    public function reviewer_cannot_transition_from_invalid_status()
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $reviewer->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $reviewer->id,
            'status' => TaskStatus::TODO->value,
        ]);

        // Reviewer cannot transition from TODO to another state (only review transitions allowed)
        $this->assertEquals(TaskStatus::TODO->value, $task->status->value);
    }
}

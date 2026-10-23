<?php

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;

if (! function_exists('createTestUsers')) {
    function createTestUsers()
    {
        // Ensure migrations run
        Artisan::call('migrate', ['--force' => true]);

        // Seed roles and permissions
        (new RolePermissionSeeder)->run();

        $admin = User::factory()->create([
            'email' => 'admin@projex.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('administrator');

        $pm = User::factory()->create([
            'email' => 'pm@projex.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $pm->assignRole('project_manager');

        $member = User::factory()->create([
            'email' => 'member@projex.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $member->assignRole('member');

        return [$admin, $pm, $member];
    }
}

if (! function_exists('createTestProjects')) {
    function createTestProjects($admin, $pm, $member)
    {
        $project = Project::create([
            'name' => 'Redesain Website Perusahaan',
            'description' => 'Test project',
            'status' => ProjectStatus::ACTIVE,
            'priority' => ProjectPriority::HIGH,
            'start_date' => now()->subWeeks(4),
            'end_date' => now()->addWeeks(4),
            'owner_id' => $admin->id,
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'role' => ProjectRole::OWNER,
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $pm->id,
            'role' => ProjectRole::MANAGER,
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $pmProject = Project::create([
            'name' => 'Sistem Manajemen Inventaris',
            'description' => 'PM project',
            'status' => ProjectStatus::DRAFT,
            'priority' => ProjectPriority::MEDIUM,
            'start_date' => now()->addWeeks(2),
            'end_date' => now()->addMonths(3),
            'owner_id' => $pm->id,
        ]);

        ProjectMember::create([
            'project_id' => $pmProject->id,
            'user_id' => $pm->id,
            'role' => ProjectRole::OWNER,
        ]);

        ProjectMember::create([
            'project_id' => $pmProject->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $otherProject = Project::create([
            'name' => 'Aplikasi Mobile Internal',
            'description' => 'Other project',
            'status' => ProjectStatus::ON_HOLD,
            'priority' => ProjectPriority::LOW,
            'owner_id' => $pm->id,
        ]);

        ProjectMember::create([
            'project_id' => $otherProject->id,
            'user_id' => $pm->id,
            'role' => ProjectRole::OWNER,
        ]);

        return [$project, $pmProject, $otherProject];
    }
}

if (! function_exists('createTestTasks')) {
    function createTestTasks($admin, $pm, $member, $project, $pmProject, $otherProject)
    {
        $task1 = Task::create([
            'project_id' => $project->id,
            'title' => 'Design Homepage',
            'description' => 'Create homepage design',
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'assignee_id' => $member->id,
            'creator_id' => $admin->id,
            'start_date' => now()->subDays(5),
            'due_date' => now()->addDays(10),
        ]);

        $task2 = Task::create([
            'project_id' => $project->id,
            'title' => 'Implement API',
            'description' => 'Build REST API endpoints',
            'status' => TaskStatus::IN_PROGRESS,
            'priority' => TaskPriority::MEDIUM,
            'assignee_id' => $pm->id,
            'creator_id' => $pm->id,
            'start_date' => now()->subDays(3),
            'due_date' => now()->addDays(7),
        ]);

        $task3 = Task::create([
            'project_id' => $pmProject->id,
            'title' => 'Database Migration',
            'description' => 'Run database migrations',
            'status' => TaskStatus::DONE,
            'priority' => TaskPriority::URGENT,
            'assignee_id' => $member->id,
            'creator_id' => $pm->id,
            'start_date' => now()->subDays(10),
            'due_date' => now()->subDays(2),
            'completed_at' => now()->subDays(1),
        ]);

        $task4 = Task::create([
            'project_id' => $otherProject->id,
            'title' => 'Mobile App Design',
            'description' => 'Design mobile app UI',
            'status' => TaskStatus::REVIEW,
            'priority' => TaskPriority::LOW,
            'assignee_id' => $admin->id,
            'creator_id' => $pm->id,
            'due_date' => now()->addDays(14),
        ]);

        return [$task1, $task2, $task3, $task4];
    }
}

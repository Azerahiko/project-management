<?php

require_once __DIR__.'/../helpers.php';

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Dashboard Controller', function () {
    beforeEach(function () {
        Artisan::call('migrate', ['--force' => true]);
        (new RolePermissionSeeder)->run();
    });

    describe('Statistics accuracy', function () {
        it('admin sees global stats across all projects', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, $pmProject, $otherProject] = createTestProjects($admin, $pm, $member);
            [$task1, $task2, $task3, $task4] = createTestTasks($admin, $pm, $member, $project, $pmProject, $otherProject);

            // Add an overdue task
            Task::create([
                'project_id' => $project->id,
                'title' => 'Overdue Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $member->id,
                'creator_id' => $admin->id,
                'due_date' => now()->subDay(),
            ]);

            // Add a DONE task with past due date (should NOT be overdue)
            Task::create([
                'project_id' => $project->id,
                'title' => 'Done Overdue Task',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $member->id,
                'creator_id' => $admin->id,
                'due_date' => now()->subDay(),
                'completed_at' => now()->subHour(),
            ]);

            $response = $this->actingAs($admin)->get(route('dashboard'));
            $response->assertOk();

            $stats = $response->inertiaProps('stats');

            // Admin sees all 3 projects but only 1 is ACTIVE
            expect($stats['projects_count'])->toBe(1);

            // Admin is assignee of task4 (in otherProject, ON_HOLD, REVIEW)
            expect($stats['my_tasks_count'])->toBe(1);

            // All accessible tasks that are not DONE (all 6 tasks minus 2 done)
            expect($stats['active_tasks_count'])->toBe(4);

            // 1 overdue task (the one we added), task3 is DONE so not counted
            expect($stats['overdue_count'])->toBe(1);

            // Admin + PM + member = 3 unique members across accessible projects
            expect($stats['team_members_count'])->toBe(3);

            expect($stats['notifications_unread'])->toBe(0);
        });

        it('project_manager sees only owned and member projects', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, $pmProject, $otherProject] = createTestProjects($admin, $pm, $member);

            $response = $this->actingAs($pm)->get(route('dashboard'));
            $response->assertOk();

            $stats = $response->inertiaProps('stats');
            expect($stats['projects_count'])->toBe(1); // project is ACTIVE
        });

        it('team_leader sees unique members from accessible projects', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project] = createTestProjects($admin, $pm, $member);

            $teamLeader = User::factory()->create(['is_active' => true]);
            $teamLeader->assignRole('team_leader');

            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $teamLeader->id,
                'role' => ProjectRole::MEMBER,
            ]);

            $response = $this->actingAs($teamLeader)->get(route('dashboard'));
            $response->assertOk();

            $stats = $response->inertiaProps('stats');
            expect($stats['team_members_count'])->toBe(4);
        });

        it('member sees only assigned tasks in my_tasks_count', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, $pmProject, $otherProject] = createTestProjects($admin, $pm, $member);
            [$task1, $task2, $task3, $task4] = createTestTasks($admin, $pm, $member, $project, $pmProject, $otherProject);

            $response = $this->actingAs($member)->get(route('dashboard'));
            $response->assertOk();

            $stats = $response->inertiaProps('stats');
            expect($stats['my_tasks_count'])->toBe(1);
        });

        it('overdue count excludes DONE tasks even with past due date', function () {
            [$admin] = createTestUsers();
            $project = Project::create([
                'name' => 'Test Project',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'owner_id' => $admin->id,
            ]);
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $admin->id,
                'role' => ProjectRole::OWNER,
            ]);

            // Overdue TODO task
            Task::create([
                'project_id' => $project->id,
                'title' => 'Overdue TODO',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->subDay(),
            ]);

            // Overdue IN_PROGRESS task
            Task::create([
                'project_id' => $project->id,
                'title' => 'Overdue IN_PROGRESS',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->subDay(),
            ]);

            // DONE task with past due date - should NOT be overdue
            Task::create([
                'project_id' => $project->id,
                'title' => 'Done but was overdue',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->subDay(),
                'completed_at' => now(),
            ]);

            $response = $this->actingAs($admin)->get(route('dashboard'));
            $stats = $response->inertiaProps('stats');

            expect($stats['overdue_count'])->toBe(2);
        });
    });

    describe('Data isolation per role', function () {
        it('client does not receive activities or internal comments', function () {
            [$admin, $pm, $member] = createTestUsers();

            // Create client user
            $client = User::factory()->create([
                'email' => 'client@projex.test',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
            $client->assignRole('client');

            $project = Project::create([
                'name' => 'Client Project',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'owner_id' => $admin->id,
            ]);

            // Add client as VIEWER
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $client->id,
                'role' => ProjectRole::VIEWER,
            ]);

            // Add activity
            $task = Task::create([
                'project_id' => $project->id,
                'title' => 'Client Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $member->id,
                'creator_id' => $admin->id,
            ]);

            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $admin->id,
                'activity_type' => 'task.created',
                'description' => 'Task created',
            ]);

            $response = $this->actingAs($client)->get(route('dashboard'));
            $response->assertOk();

            $activities = $response->inertiaProps('recent_activities');

            // Client should not see activities (no task-collaboration.view permission)
            expect($activities)->toBeEmpty();
        });

        it('reviewer sees activities for assigned tasks', function () {
            [$admin, $pm, $member] = createTestUsers();

            $reviewer = User::factory()->create([
                'email' => 'reviewer@projex.test',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
            $reviewer->assignRole('reviewer');

            $project = Project::create([
                'name' => 'Review Project',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'owner_id' => $admin->id,
            ]);

            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $reviewer->id,
                'role' => ProjectRole::MEMBER,
            ]);

            $task = Task::create([
                'project_id' => $project->id,
                'title' => 'Review Task',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $reviewer->id,
                'creator_id' => $admin->id,
            ]);

            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $admin->id,
                'activity_type' => 'task.created',
                'description' => 'Task created',
            ]);

            $response = $this->actingAs($reviewer)->get(route('dashboard'));
            $response->assertOk();

            $activities = $response->inertiaProps('recent_activities');

            // Reviewer has task-collaboration.view, should see activities
            expect($activities)->not()->toBeEmpty();
        });
    });

    describe('Deadline urgency classification', function () {
        it('classifies deadline boundaries correctly', function () {
            [$admin] = createTestUsers();
            $project = Project::create([
                'name' => 'Deadline Project',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'owner_id' => $admin->id,
            ]);
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $admin->id,
                'role' => ProjectRole::OWNER,
            ]);

            // Overdue (due in past - yesterday)
            Task::create([
                'project_id' => $project->id,
                'title' => 'Overdue Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->subDay(),
            ]);

            // Critical: due today + 12 hours (< 24h) - use startOfDay to ensure same day
            Task::create([
                'project_id' => $project->id,
                'title' => 'Critical Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->startOfDay()->addHours(12),
            ]);

            // Critical: due today + 23 hours (still < 24h)
            Task::create([
                'project_id' => $project->id,
                'title' => 'Critical 23h Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->startOfDay()->addHours(23),
            ]);

            // Warning: due tomorrow (24-72h range)
            Task::create([
                'project_id' => $project->id,
                'title' => 'Warning Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->startOfDay()->addDay(),
            ]);

            // Warning: due in 2 days (48h)
            Task::create([
                'project_id' => $project->id,
                'title' => 'Warning 2d Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->startOfDay()->addDays(2),
            ]);

            // Normal: due in 3 days (72h+) - this will be 6th, not in top 5
            Task::create([
                'project_id' => $project->id,
                'title' => 'Normal Task',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'due_date' => now()->startOfDay()->addDays(3),
            ]);

            $response = $this->actingAs($admin)->get(route('dashboard'));
            $deadlines = $response->inertiaProps('upcoming_deadlines');

            // Find each task by title and verify urgency
            $overdue = collect($deadlines)->firstWhere('title', 'Overdue Task');
            expect($overdue['deadline_urgency'])->toBe('overdue');

            $critical1 = collect($deadlines)->firstWhere('title', 'Critical Task');
            expect($critical1['deadline_urgency'])->toBe('critical');

            $critical2 = collect($deadlines)->firstWhere('title', 'Critical 23h Task');
            // 23 hours should be 'critical' (< 24h)
            expect($critical2['deadline_urgency'])->toBe('critical');

            $warning1 = collect($deadlines)->firstWhere('title', 'Warning Task');
            // tomorrow = warning
            expect($warning1['deadline_urgency'])->toBe('warning');

            $warning2 = collect($deadlines)->firstWhere('title', 'Warning 2d Task');
            // 2 days = warning
            expect($warning2['deadline_urgency'])->toBe('warning');
        });
    });

    describe('Tasks by status', function () {
        it('includes done tasks in the status breakdown', function () {
            [$admin, $pm, $member] = createTestUsers();
            [$project, $pmProject, $otherProject] = createTestProjects($admin, $pm, $member);
            createTestTasks($admin, $pm, $member, $project, $pmProject, $otherProject);

            $response = $this->actingAs($admin)->get(route('dashboard'));
            $tasksByStatus = $response->inertiaProps('tasks_by_status');

            expect($tasksByStatus['todo'])->toBe(1);
            expect($tasksByStatus['in_progress'])->toBe(1);
            expect($tasksByStatus['review'])->toBe(1);
            expect($tasksByStatus['done'])->toBe(1);
        });
    });

    describe('Project progress', function () {
        it('calculates progress percentage correctly', function () {
            [$admin] = createTestUsers();
            $project = Project::create([
                'name' => 'Progress Project',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'owner_id' => $admin->id,
            ]);
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $admin->id,
                'role' => ProjectRole::OWNER,
            ]);

            // 4 tasks: 1 done, 3 not done = 25%
            Task::create([
                'project_id' => $project->id,
                'title' => 'Task 1',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
            ]);
            Task::create([
                'project_id' => $project->id,
                'title' => 'Task 2',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
            ]);
            Task::create([
                'project_id' => $project->id,
                'title' => 'Task 3',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
            ]);
            Task::create([
                'project_id' => $project->id,
                'title' => 'Task 4',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
            ]);

            $response = $this->actingAs($admin)->get(route('dashboard'));
            $progress = $response->inertiaProps('project_progress');

            expect($progress)->toHaveCount(1);
            expect($progress[0]['tasks_count'])->toBe(4);
            expect($progress[0]['completed_tasks_count'])->toBe(1);
            expect($progress[0]['progress_percent'])->toBe(25);
        });

        it('returns 0% for project with no tasks', function () {
            [$admin] = createTestUsers();
            $project = Project::create([
                'name' => 'Empty Project',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'owner_id' => $admin->id,
            ]);
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $admin->id,
                'role' => ProjectRole::OWNER,
            ]);

            $response = $this->actingAs($admin)->get(route('dashboard'));
            $progress = $response->inertiaProps('project_progress');

            expect($progress[0]['progress_percent'])->toBe(0);
            expect($progress[0]['tasks_count'])->toBe(0);
            expect($progress[0]['completed_tasks_count'])->toBe(0);
        });
    });

    describe('Empty state', function () {
        it('returns zeros and empty arrays for user with no data', function () {
            [$admin] = createTestUsers();

            $response = $this->actingAs($admin)->get(route('dashboard'));
            $response->assertOk();

            $stats = $response->inertiaProps('stats');
            $tasksByStatus = $response->inertiaProps('tasks_by_status');
            $upcomingDeadlines = $response->inertiaProps('upcoming_deadlines');
            $recentActivities = $response->inertiaProps('recent_activities');
            $projectProgress = $response->inertiaProps('project_progress');

            expect($stats['projects_count'])->toBe(0);
            expect($stats['my_tasks_count'])->toBe(0);
            expect($stats['overdue_count'])->toBe(0);
            expect($stats['team_members_count'])->toBe(0);
            expect($tasksByStatus)->toBeEmpty();
            expect($upcomingDeadlines)->toBeEmpty();
            expect($recentActivities)->toBeEmpty();
            expect($projectProgress)->toBeEmpty();
        });
    });

    describe('Query efficiency', function () {
        it('does not produce N+1 queries with multiple projects and tasks', function () {
            [$admin] = createTestUsers();

            // Create 5 projects with tasks each
            for ($i = 1; $i <= 5; $i++) {
                $project = Project::create([
                    'name' => "Project {$i}",
                    'status' => ProjectStatus::ACTIVE,
                    'priority' => ProjectPriority::MEDIUM,
                    'owner_id' => $admin->id,
                ]);
                ProjectMember::create([
                    'project_id' => $project->id,
                    'user_id' => $admin->id,
                    'role' => ProjectRole::OWNER,
                ]);

                for ($j = 1; $j <= 3; $j++) {
                    Task::create([
                        'project_id' => $project->id,
                        'title' => "Task {$j} in Project {$i}",
                        'status' => TaskStatus::TODO,
                        'priority' => TaskPriority::MEDIUM,
                        'assignee_id' => $admin->id,
                        'creator_id' => $admin->id,
                        'due_date' => now()->addDays($j),
                    ]);
                }
            }

            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('dashboard'));
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            // Expect reasonable query count (not N+1 per project/task)
            // With 5 projects, 15 tasks: should be under 20 queries (not linear with tasks)
            expect(count($queries))->toBeLessThan(20);
        });
    });
});

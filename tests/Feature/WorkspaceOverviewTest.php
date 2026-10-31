<?php

require_once __DIR__.'/helpers.php';

use App\Models\TaskActivity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);
    (new RolePermissionSeeder)->run();
});

it('exposes raw member roles on the teams page', function () {
    [$admin, $pm, $member] = createTestUsers();
    [$project] = createTestProjects($admin, $pm, $member);

    $response = $this->actingAs($admin)->get(route('teams'));

    $response->assertOk();

    $team = collect($response->inertiaProps('projects'))->firstWhere('id', $project->id);

    expect($team)->not->toBeNull()
        ->and(collect($team['members'])->pluck('role')->all())
        ->toContain('owner', 'manager', 'member');
});

it('exposes project status values and integer task counts on reports', function () {
    [$admin, $pm, $member] = createTestUsers();
    [$project, $pmProject, $otherProject] = createTestProjects($admin, $pm, $member);
    createTestTasks($admin, $pm, $member, $project, $pmProject, $otherProject);

    $response = $this->actingAs($admin)->get(route('reports'));

    $response->assertOk();

    $report = collect($response->inertiaProps('projects'))->firstWhere('id', $project->id);

    expect($report)->not->toBeNull()
        ->and($report['status'])->toBe('active')
        ->and($report['tasks_count'])->toBeInt()
        ->and($report['completed_tasks_count'])->toBeInt();

    $taskCounts = $response->inertiaProps('task_counts');

    expect($taskCounts)->not->toBeEmpty();

    foreach ($taskCounts as $count) {
        expect($count)->toBeInt();
    }
});

it('filters the activity log by keyword and activity type', function () {
    [$admin, $pm, $member] = createTestUsers();
    [$project, $pmProject, $otherProject] = createTestProjects($admin, $pm, $member);
    [$task] = createTestTasks($admin, $pm, $member, $project, $pmProject, $otherProject);

    TaskActivity::factory()->create([
        'task_id' => $task->id,
        'user_id' => $admin->id,
        'activity_type' => 'task.updated',
        'description' => 'System Administrator mengubah status tugas menjadi Sedang Dikerjakan.',
    ]);

    TaskActivity::factory()->create([
        'task_id' => $task->id,
        'user_id' => $pm->id,
        'activity_type' => 'comment.created',
        'description' => 'Project Manager menambahkan komentar pada desain.',
    ]);

    $searchResponse = $this->actingAs($admin)->get(route('activity-log', ['search' => 'komentar']));

    $searchResponse->assertOk();

    $searchResults = collect($searchResponse->inertiaProps('activities')['data']);

    expect($searchResults)->toHaveCount(1)
        ->and($searchResults->first()['activity_type'])->toBe('comment.created')
        ->and($searchResponse->inertiaProps('stats.comment_activities'))->toBe(1)
        ->and($searchResponse->inertiaProps('stats.task_activities'))->toBe(0);

    $typeResponse = $this->actingAs($admin)->get(route('activity-log', ['type' => 'task']));

    $typeResponse->assertOk();

    $typeResults = collect($typeResponse->inertiaProps('activities')['data']);

    expect($typeResults)->toHaveCount(1)
        ->and($typeResults->first()['activity_type'])->toBe('task.updated')
        ->and($typeResponse->inertiaProps('stats.total'))->toBe(2);
});

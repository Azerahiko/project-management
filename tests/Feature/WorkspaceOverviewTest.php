<?php

require_once __DIR__.'/helpers.php';

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

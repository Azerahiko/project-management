<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ProjectRole;
use App\Models\ProjectMember;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);
    (new RolePermissionSeeder)->run();
});

it('shows accessible project tasks on the timeline', function () {
    [$admin, $pm, $member] = createTestUsers();
    [$project] = createTestProjects($admin, $pm, $member);
    [$task] = createTestTasks($admin, $pm, $member, $project, $project, $project);

    $response = $this->actingAs($member)->get(route('timeline'));

    $response->assertOk();

    $timelineProject = collect($response->inertiaProps('projects'))
        ->firstWhere('id', $project->id);

    expect($timelineProject)->not->toBeNull()
        ->and(collect($timelineProject['tasks'])->pluck('id'))->toContain($task->id)
        ->and($timelineProject['tasks'][0])->toHaveKeys([
            'start_date',
            'due_date',
            'status_label',
            'priority_label',
        ]);
});

it('allows clients to view the timeline for their accessible projects', function () {
    [$admin, $pm, $member] = createTestUsers();
    [$project] = createTestProjects($admin, $pm, $member);

    $client = User::factory()->create(['is_active' => true]);
    $client->assignRole('client');

    ProjectMember::create([
        'project_id' => $project->id,
        'user_id' => $client->id,
        'role' => ProjectRole::VIEWER,
    ]);

    $this->actingAs($client)->get(route('timeline'))->assertOk();
});

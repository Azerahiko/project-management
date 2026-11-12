<?php

require_once __DIR__.'/helpers.php';

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Timeline undated tasks', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('administrator');
        $this->project = Project::factory()->create(['owner_id' => $this->admin->id]);
    });

    it('returns dated tasks in tasks and undated tasks in tasks_with_undated', function () {
        $dated = Task::factory()->create([
            'project_id' => $this->project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Dated task',
            'start_date' => now(),
            'due_date' => now()->addDays(3),
        ]);

        $undated = Task::factory()->create([
            'project_id' => $this->project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Undated task',
            'start_date' => null,
            'due_date' => null,
        ]);

        $response = $this->actingAs($this->admin)->get('/timeline');
        $response->assertOk();

        $project = collect($response->inertiaProps('projects'))->firstWhere('id', $this->project->id);

        expect($project)->not->toBeNull()
            ->and(collect($project['tasks'])->pluck('id')->all())->toBe([$dated->id])
            ->and(collect($project['tasks_with_undated'])->pluck('id')->all())->toBe([$undated->id]);
    });

    it('keeps tasks that only have a start_date in the dated section', function () {
        $startOnly = Task::factory()->create([
            'project_id' => $this->project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Start only',
            'start_date' => now(),
            'due_date' => null,
        ]);

        $response = $this->actingAs($this->admin)->get('/timeline');

        $project = collect($response->inertiaProps('projects'))->firstWhere('id', $this->project->id);

        expect(collect($project['tasks'])->pluck('id')->all())->toBe([$startOnly->id])
            ->and($project['tasks_with_undated'])->toBe([]);
    });

    it('keeps tasks that only have a due_date in the dated section', function () {
        $dueOnly = Task::factory()->create([
            'project_id' => $this->project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Due only',
            'start_date' => null,
            'due_date' => now()->addDays(2),
        ]);

        $response = $this->actingAs($this->admin)->get('/timeline');

        $project = collect($response->inertiaProps('projects'))->firstWhere('id', $this->project->id);

        expect(collect($project['tasks'])->pluck('id')->all())->toBe([$dueOnly->id])
            ->and($project['tasks_with_undated'])->toBe([]);
    });
});

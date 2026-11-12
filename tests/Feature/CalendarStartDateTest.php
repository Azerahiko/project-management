<?php

require_once __DIR__.'/helpers.php';

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Calendar start_date query', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('administrator');
    });

    it('includes tasks with only due_date in calendar', function () {
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);

        // Task with only due_date (existing behavior)
        $taskWithDueDate = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Task with only due date',
            'start_date' => null,
            'due_date' => now()->addDays(5),
        ]);

        $response = $this->actingAs($this->admin)->get('/calendar?month='.now()->format('Y-m'));
        $response->assertOk();

        $calendarTasks = $response->inertiaProps('tasks');
        $foundTask = collect($calendarTasks)->firstWhere('id', $taskWithDueDate->id);
        expect($foundTask)->not->toBeNull()
            ->and($foundTask['due_date'])->toBe($taskWithDueDate->due_date->toDateString())
            ->and($foundTask['start_date'])->toBeNull();
    });

    it('includes tasks with only start_date in calendar', function () {
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);

        // Task with only start_date (new behavior after F1 fix)
        $taskWithStartDate = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Task with only start date',
            'start_date' => now()->addDays(3),
            'due_date' => null,
        ]);

        $response = $this->actingAs($this->admin)->get('/calendar?month='.now()->format('Y-m'));
        $response->assertOk();

        $calendarTasks = $response->inertiaProps('tasks');
        $foundTask = collect($calendarTasks)->firstWhere('id', $taskWithStartDate->id);
        expect($foundTask)->not->toBeNull()
            ->and($foundTask['due_date'])->toBeNull()
            ->and($foundTask['start_date'])->toBe($taskWithStartDate->start_date->toDateString());
    });

    it('includes tasks with both start_date and due_date in calendar', function () {
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);

        // Task with both start_date and due_date (existing behavior)
        $taskWithBothDates = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Task with both dates',
            'start_date' => now()->addDays(2),
            'due_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->admin)->get('/calendar?month='.now()->format('Y-m'));
        $response->assertOk();

        $calendarTasks = $response->inertiaProps('tasks');
        $foundTask = collect($calendarTasks)->firstWhere('id', $taskWithBothDates->id);
        expect($foundTask)->not->toBeNull()
            ->and($foundTask['due_date'])->toBe($taskWithBothDates->due_date->toDateString())
            ->and($foundTask['start_date'])->toBe($taskWithBothDates->start_date->toDateString());
    });

    it('excludes tasks with neither start_date nor due_date from calendar', function () {
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);

        // Task with neither start_date nor due_date (should be excluded)
        $taskWithNoDates = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $this->admin->id,
            'title' => 'Task with no dates',
            'start_date' => null,
            'due_date' => null,
        ]);

        $response = $this->actingAs($this->admin)->get('/calendar?month='.now()->format('Y-m'));
        $response->assertOk();

        $calendarTasks = $response->inertiaProps('tasks');
        $foundTask = collect($calendarTasks)->firstWhere('id', $taskWithNoDates->id);
        expect($foundTask)->toBeNull();
    });

});

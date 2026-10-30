<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    /**
     * Display the Gantt timeline for accessible projects.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(Permissions::PROJECTS_VIEW);

        $projects = Project::accessibleBy($request->user())
            ->with([
                'tasks' => fn ($query) => $query
                    ->where(function ($query): void {
                        $query->whereNotNull('start_date')
                            ->orWhereNotNull('due_date');
                    })
                    ->with('assignee:id,name,avatar')
                    ->orderByRaw('COALESCE(start_date, due_date) asc')
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'start_date', 'end_date', 'owner_id']);

        return Inertia::render('Timeline/Index', [
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'tasks' => $project->tasks->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status->value,
                    'status_label' => $task->status->label(),
                    'priority' => $task->priority->value,
                    'priority_label' => $task->priority->label(),
                    'start_date' => $task->start_date?->toDateString(),
                    'due_date' => $task->due_date?->toDateString(),
                    'is_overdue' => $task->due_date !== null
                        && $task->status !== TaskStatus::DONE
                        && $task->due_date->isBefore(today()),
                    'assignee' => $task->assignee ? [
                        'id' => $task->assignee->id,
                        'name' => $task->assignee->name,
                        'avatar' => $task->assignee->avatar,
                        'initials' => $task->assignee->initials,
                    ] : null,
                ]),
            ]),
            'can_view_tasks' => $request->user()->can(Permissions::TASKS_VIEW),
        ]);
    }
}

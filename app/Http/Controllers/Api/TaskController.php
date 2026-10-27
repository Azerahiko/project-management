<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskStatusChanged;
use App\Services\NotificationSender;
use App\Services\TaskActivityLogger;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function __construct(
        protected TaskActivityLogger $activityLogger,
        protected NotificationSender $notificationSender
    ) {}

    /**
     * Display a paginated listing of tasks.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->with(['project', 'assignee', 'creator'])
            ->accessibleBy($request->user())
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $query->search($request->string('search')->toString());
            })
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request) {
                $query->status($request->string('status')->toString());
            })
            ->when($request->string('priority')->isNotEmpty(), function ($query) use ($request) {
                $query->priority($request->string('priority')->toString());
            })
            ->when($request->has('assignee_id'), function ($query) use ($request) {
                $query->assignee($request->integer('assignee_id'));
            })
            ->when($request->has('project_id'), function ($query) use ($request) {
                $query->project($request->integer('project_id'));
            })
            ->when($request->string('sort_by')->isNotEmpty(), function ($query) use ($request) {
                $query->sort(
                    $request->string('sort_by')->toString(),
                    $request->string('sort_direction')->toString() === 'desc' ? 'desc' : 'asc'
                );
            }, fn ($query) => $query->latest())
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        // Add authorization flags to each task
        $tasks->getCollection()->transform(function ($task) use ($request) {
            $task->can_update = $request->user()?->can('update', $task) ?? false;
            $task->can_delete = $request->user()?->can('delete', $task) ?? false;

            return $task;
        });

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Ensure creator_id is set
            if (! isset($data['creator_id'])) {
                $data['creator_id'] = $request->user()->id;
            }

            // If status is done, set completed_at
            if (($data['status'] ?? TaskStatus::TODO->value) === 'done') {
                $data['completed_at'] = now();
            }

            $task = Task::create($data);

            $this->activityLogger->log(
                $task,
                'task.created',
                null,
                null,
                $task->title,
                $request->user()
            );

            return $task->load(['project', 'assignee', 'creator']);
        });

        // Send TaskAssigned notification after commit if assignee is set and not the creator
        if ($task->assignee_id && $task->assignee_id !== $request->user()->id) {
            $eventId = Str::uuid()->toString();
            DB::afterCommit(function () use ($task, $eventId) {
                $this->notificationSender->send($task->assignee, TaskAssigned::class, [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project->name,
                    'action' => 'assigned',
                    'event_id' => $eventId,
                ]);
            });
        }

        return TaskResource::make($task)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified task.
     */
    public function show(Request $request, Task $task): TaskResource
    {
        $this->authorize('view', $task);

        $task->load(['project', 'assignee', 'creator']);
        $task->can_update = $request->user()?->can('update', $task) ?? false;
        $task->can_delete = $request->user()?->can('delete', $task) ?? false;

        return TaskResource::make($task);
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $data = $request->validated();

        // Capture original raw values for activity logging AND change detection (before fill)
        $original = $task->getAttributes();

        // Handle status change to/from done
        if (isset($data['status'])) {
            if ($data['status'] === 'done' && $task->status->value !== 'done') {
                $data['completed_at'] = now();
            } elseif ($data['status'] !== 'done' && $task->status->value === 'done') {
                $data['completed_at'] = null;
            }
        }

        // Check for changes BEFORE fill/save
        $assigneeChanged = isset($data['assignee_id']) && $data['assignee_id'] !== $original['assignee_id'];
        $statusChanged = isset($data['status']) && $data['status'] !== $original['status'];

        $task->fill($data);
        $task->save();

        // Log activity for each changed field using getChanges()
        $activities = [];
        $fieldsToWatch = ['status', 'priority', 'assignee_id', 'due_date', 'start_date'];

        foreach ($fieldsToWatch as $field) {
            if (array_key_exists($field, $task->getChanges())) {
                $activities[] = ['task.updated', $field, $original[$field] ?? null, $data[$field] ?? null];
            }
        }

        if ($activities !== []) {
            $this->activityLogger->logMultiple($task, $activities, $request->user());
        }

        // Send TaskAssigned notification if assignee changed
        if ($assigneeChanged && $task->assignee_id && $task->assignee_id !== $request->user()->id) {
            $eventId = Str::uuid()->toString();
            DB::afterCommit(function () use ($task, $eventId) {
                // Load relationships if not loaded
                if (! $task->relationLoaded('assignee')) {
                    $task->load('assignee');
                }
                if (! $task->relationLoaded('project')) {
                    $task->load('project');
                }
                $this->notificationSender->send($task->assignee, TaskAssigned::class, [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project->name,
                    'action' => 'assigned',
                    'event_id' => $eventId,
                ]);
            });
        }

        // Send TaskStatusChanged notification if status changed
        if ($statusChanged) {
            $oldStatus = $original['status'] ?? null;
            $newStatus = $task->status->value;
            $recipients = $this->getStatusChangeRecipients($task, $request->user());

            if ($recipients->isNotEmpty()) {
                $eventId = Str::uuid()->toString();
                DB::afterCommit(function () use ($task, $recipients, $oldStatus, $newStatus, $eventId) {
                    foreach ($recipients as $recipient) {
                        $this->notificationSender->send($recipient, TaskStatusChanged::class, [
                            'task_id' => $task->id,
                            'task_title' => $task->title,
                            'project_id' => $task->project_id,
                            'project_name' => $task->project->name,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'action' => 'status_changed',
                            'event_id' => $eventId,
                        ]);
                    }
                });
            }
        }

        return TaskResource::make($task->load(['project', 'assignee', 'creator']));
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        DB::transaction(function () use ($task, $request): void {
            $this->activityLogger->log(
                $task,
                'task.deleted',
                null,
                null,
                $task->title,
                $request->user()
            );

            $task->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * Get available assignees for a project (project members).
     */
    public function assignees(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $assignees = $project->members()
            ->where('users.is_active', true)
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email', 'users.avatar']);

        return response()->json([
            'data' => $assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'initials' => $user->initials,
            ]),
        ]);
    }

    /**
     * Get unique recipients for task status change notifications.
     * Excludes the actor and deduplicates by user ID.
     *
     * @return Collection<int, User>
     */
    private function getStatusChangeRecipients(Task $task, User $actor): Collection
    {
        $recipients = collect();

        // Assignee
        if ($task->assignee_id && $task->assignee_id !== $actor->id) {
            $recipients->push($task->assignee);
        }

        // Creator
        if ($task->creator_id !== $actor->id && $task->creator_id !== ($task->assignee_id ?? 0)) {
            $recipients->push($task->creator);
        }

        // Project owners & managers (excluding actor, assignee, creator)
        $projectMembers = $task->project->members()
            ->wherePivotIn('role', ['owner', 'manager'])
            ->where('users.id', '!=', $actor->id)
            ->where('users.id', '!=', $task->assignee_id ?? 0)
            ->where('users.id', '!=', $task->creator_id)
            ->get();

        $recipients = $recipients->merge($projectMembers);

        // Deduplicate by user ID
        return $recipients->unique('id');
    }
}

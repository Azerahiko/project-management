<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceOverviewController extends Controller
{
    public function calendar(Request $request): Response
    {
        Gate::authorize(Permissions::CALENDAR_VIEW);

        $validated = $request->validate(['month' => ['sometimes', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $validated['month'] ?? now()->format('Y-m'));
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $tasks = Task::accessibleBy($request->user())
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->with(['project:id,name', 'assignee:id,name,avatar'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'project_id', 'title', 'status', 'priority', 'assignee_id', 'due_date']);

        return Inertia::render('Calendar/Index', [
            'month' => $start->format('Y-m'),
            'tasks' => $tasks->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'priority' => $task->priority->value,
                'priority_label' => $task->priority->label(),
                'priority_color' => $task->priority->color(),
                'due_date' => $task->due_date->toDateString(),
                'project_name' => $task->project->name,
                'assignee' => $task->assignee
                    ? [
                        'id' => $task->assignee->id,
                        'name' => $task->assignee->name,
                        'avatar' => $task->assignee->avatar,
                        'initials' => $task->assignee->initials,
                    ]
                    : null,
            ]),
            'can_view_tasks' => $request->user()->can('tasks.view'),
        ]);
    }

    public function teams(Request $request): Response
    {
        Gate::authorize(Permissions::TEAMS_VIEW);

        $projects = Project::accessibleBy($request->user())
            ->with(['projectMembers.user:id,name,avatar'])
            ->orderBy('name')
            ->get(['id', 'name', 'owner_id']);

        return Inertia::render('Teams/Index', [
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'members' => $project->projectMembers->map(fn (ProjectMember $member): array => [
                    'id' => $member->user_id,
                    'name' => $member->user->name,
                    'avatar' => $member->user->avatar,
                    'role' => $member->role->value,
                ]),
            ]),
            'can_view_projects' => $request->user()->can('projects.view'),
        ]);
    }

    public function documents(Request $request): Response
    {
        Gate::authorize(Permissions::DOCUMENTS_VIEW);

        $projects = Project::accessibleBy($request->user())
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'owner_id']);

        return Inertia::render('Documents/Index', [
            'project_notes' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
            ]),
            'can_view_projects' => $request->user()->can('projects.view'),
        ]);
    }

    public function reports(Request $request): Response
    {
        Gate::authorize(Permissions::REPORTS_VIEW);

        $projects = Project::accessibleBy($request->user())
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->where('status', TaskStatus::DONE),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'owner_id']);

        $taskCounts = Task::accessibleBy($request->user())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total);

        return Inertia::render('Reports/Index', [
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status->value,
                'tasks_count' => $project->tasks_count,
                'completed_tasks_count' => $project->completed_tasks_count,
            ]),
            'task_counts' => $taskCounts,
            'can_view_projects' => $request->user()->can('projects.view'),
        ]);
    }

    public function activityLog(Request $request): Response
    {
        Gate::authorize(Permissions::ACTIVITY_LOG_VIEW);

        $activities = TaskActivity::whereHas('task.project', fn ($query) => $query->accessibleBy($request->user()))
            ->with(['user:id,name', 'task:id,project_id,title'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ActivityLog/Index', [
            'activities' => $activities->through(fn (TaskActivity $activity): array => [
                'id' => $activity->id,
                'description' => $activity->description,
                'created_at' => $activity->created_at?->toISOString(),
                'user_name' => $activity->user->name ?? 'Pengguna tidak tersedia',
                'task_id' => $activity->task->id,
                'task_title' => $activity->task->title,
            ]),
            'can_view_tasks' => $request->user()->can('tasks.view'),
        ]);
    }

    public function collaboration(Request $request): Response
    {
        Gate::authorize(Permissions::TASK_COLLABORATION_VIEW);

        $accessibleProjects = Project::accessibleBy($request->user());

        $comments = TaskComment::whereHas('task.project', fn ($query) => $query->accessibleBy($request->user()))
            ->with(['user:id,name,avatar', 'task.project:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $recentActivities = TaskActivity::whereHas('task.project', fn ($query) => $query->accessibleBy($request->user()))
            ->with(['user:id,name,avatar', 'task:id,title,project_id'])
            ->orderByDesc('created_at')
            ->take(8)
            ->get();

        $activeProjects = (clone $accessibleProjects)
            ->with(['owner:id,name,avatar', 'projectMembers.user:id,name,avatar'])
            ->withCount(['tasks', 'projectMembers'])
            ->orderByDesc('updated_at')
            ->take(6)
            ->get();

        $collaboratingUsers = User::query()
            ->where(function ($query) use ($request) {
                $query->whereHas('projects', fn ($q) => $q->accessibleBy($request->user()))
                    ->orWhereHas('ownedProjects', fn ($q) => $q->accessibleBy($request->user()));
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->take(12)
            ->get(['id', 'name', 'avatar']);

        $reviewTasks = Task::whereHas('project', fn ($q) => $q->accessibleBy($request->user()))
            ->where('status', TaskStatus::REVIEW)
            ->with(['assignee:id,name,avatar', 'project:id,name'])
            ->orderByDesc('updated_at')
            ->take(4)
            ->get();

        $totalCommentsCount = TaskComment::whereHas('task.project', fn ($q) => $q->accessibleBy($request->user()))->count();
        $totalReviewsCount = Task::whereHas('project', fn ($q) => $q->accessibleBy($request->user()))
            ->where('status', TaskStatus::REVIEW)
            ->count();

        return Inertia::render('Collaboration/Index', [
            'stats' => [
                'total_discussions' => $totalCommentsCount,
                'active_members' => $collaboratingUsers->count(),
                'active_projects' => $activeProjects->count(),
                'pending_reviews' => $totalReviewsCount,
            ],
            'review_tasks' => $reviewTasks->map(fn (Task $t): array => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority->value,
                'priority_label' => $t->priority->label(),
                'project_name' => $t->project->name,
                'assignee' => $t->assignee ? [
                    'name' => $t->assignee->name,
                    'avatar' => $t->assignee->avatar,
                    'initials' => $t->assignee->initials,
                ] : null,
            ]),
            'comments' => $comments->through(fn (TaskComment $comment): array => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at?->toISOString(),
                'author_name' => $comment->user->name,
                'author_avatar' => $comment->user->avatar,
                'author_initials' => $comment->user->initials,
                'task_id' => $comment->task->id,
                'task_title' => $comment->task->title,
                'project_name' => $comment->task->project->name,
            ]),
            'recent_activities' => $recentActivities->map(fn (TaskActivity $act): array => [
                'id' => $act->id,
                'activity_type' => $act->activity_type,
                'description' => $act->description,
                'created_at' => $act->created_at?->toISOString(),
                'user' => $act->user ? [
                    'id' => $act->user->id,
                    'name' => $act->user->name,
                    'avatar' => $act->user->avatar,
                    'initials' => $act->user->initials,
                ] : null,
                'task' => [
                    'id' => $act->task->id,
                    'title' => $act->task->title,
                ],
            ]),
            'active_projects' => $activeProjects->map(fn (Project $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'tasks_count' => $p->tasks_count,
                'members_count' => $p->project_members_count,
                'members' => $p->projectMembers->take(4)->map(fn ($m) => [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'avatar' => $m->user->avatar,
                    'initials' => $m->user->initials,
                ]),
            ]),
            'team_members' => $collaboratingUsers->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'initials' => $u->initials,
            ]),
            'can_view_tasks' => $request->user()->can('tasks.view'),
            'can_view_projects' => $request->user()->can('projects.view'),
        ]);
    }
}

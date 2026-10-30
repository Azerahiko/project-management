<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskActivityResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $accessibleProjects = Project::accessibleBy($user);
        $accessibleTasks = Task::accessibleBy($user);

        // Global statistics (all accessible data, not limited by display)
        $stats = [
            'projects_count' => (clone $accessibleProjects)->where('status', ProjectStatus::ACTIVE)->count(),
            'my_tasks_count' => (clone $accessibleTasks)
                ->where('assignee_id', $user->id)
                ->whereNotIn('status', [TaskStatus::DONE])->count(),
            'active_tasks_count' => (clone $accessibleTasks)
                ->whereNotIn('status', [TaskStatus::DONE])->count(),
            'overdue_count' => (clone $accessibleTasks)
                ->where('status', '!=', TaskStatus::DONE)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())->count(),
            'team_members_count' => $this->getTeamMembersCount($accessibleProjects),
            'notifications_unread' => $user->can('notifications.view')
                ? $user->unreadNotifications()->count()
                : 0,
        ];

        // Tasks by status (SQL aggregation)
        $tasksByStatus = (clone $accessibleTasks)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Upcoming deadlines (5 closest, with urgency classification)
        $upcomingDeadlines = (clone $accessibleTasks)
            ->where('status', '!=', TaskStatus::DONE)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->limit(5)
            ->with(['project:id,name'])
            ->get()
            ->map(fn ($task) => array_merge(
                TaskResource::make($task)->resolve($request),
                ['deadline_urgency' => $this->getDeadlineUrgency($task->due_date)]
            ));

        // Recent activities (10 latest, filtered by accessible projects)
        $recentActivities = $user->can('task-collaboration.view')
            ? TaskActivity::whereHas('task.project', function ($q) use ($user) {
                $q->accessibleBy($user);
            })
                ->latest()
                ->with(['user:id,name,avatar', 'task:id,title,project_id'])
                ->limit(10)
                ->get()
                ->map(fn ($a) => TaskActivityResource::make($a)->resolve($request))
            : [];

        // Project progress (max 5 for display, stats from ALL accessible)
        $projectProgress = $accessibleProjects
            ->where('status', ProjectStatus::ACTIVE)
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', TaskStatus::DONE),
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($p) => array_merge(
                ProjectResource::make($p)->resolve($request),
                [
                    'tasks_count' => $p->tasks_count,
                    'completed_tasks_count' => $p->completed_tasks_count,
                    'progress_percent' => $p->tasks_count > 0
                        ? round(($p->completed_tasks_count / $p->tasks_count) * 100)
                        : 0,
                ]
            ));

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'tasks_by_status' => $tasksByStatus,
            'upcoming_deadlines' => $upcomingDeadlines,
            'recent_activities' => $recentActivities,
            'project_progress' => $projectProgress,
        ]);
    }

    /**
     * @param  Builder<Project>  $projectsQuery
     */
    private function getTeamMembersCount($projectsQuery): int
    {
        $projectIds = (clone $projectsQuery)->pluck('id')->toArray();

        if ($projectIds === []) {
            return 0;
        }

        return \DB::table('project_members')
            ->whereIn('project_id', $projectIds)
            ->distinct('user_id')
            ->count('user_id');
    }

    private function getDeadlineUrgency(?string $dueDate): string
    {
        if (! $dueDate) {
            return 'normal';
        }

        $carbon = Carbon::parse($dueDate);

        // due_date is cast to 'date' (not datetime), so compare dates
        $today = now()->startOfDay();
        $dueDateStart = $carbon->copy()->startOfDay();

        if ($dueDateStart->lt($today)) {
            return 'overdue';
        }

        // Use floor of diffInDays to get whole days difference
        $daysUntil = (int) floor($today->diffInDays($dueDateStart, false));

        // 0 = today (< 24h) = critical (red)
        // 1 = tomorrow (~24h) = warning (yellow, 24-72h range)
        // 2 = 2 days (~48h) = warning (yellow, 24-72h range)
        // 3 = 3 days (~72h) = normal (green, >= 72h)
        // 4+ = normal (green, > 72h)
        if ($daysUntil === 0) {
            return 'critical';
        }

        if ($daysUntil <= 2) {
            return 'warning';
        }

        return 'normal';
    }
}

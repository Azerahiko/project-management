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

        // Single aggregate query for all task statistics
        $taskStats = (clone $accessibleTasks)
            ->selectRaw("
                COUNT(*) as total_count,
                SUM(CASE WHEN status != 'done' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN assignee_id = ? AND status != 'done' THEN 1 ELSE 0 END) as my_tasks_count,
                SUM(CASE WHEN status != 'done' AND due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review_count,
                SUM(CASE WHEN priority IN ('urgent', 'high') AND status != 'done' THEN 1 ELSE 0 END) as urgent_count,
                SUM(CASE WHEN status != 'done' AND due_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_this_week_count
            ", [
                $user->id,
                now()->toDateString(),
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ])
            ->first();

        // Tasks by status (SQL aggregation)
        $tasksByStatus = (clone $accessibleTasks)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Active projects count (for stats)
        $activeProjectsCount = (clone $accessibleProjects)->where('status', ProjectStatus::ACTIVE)->count();

        // Team members count (optimized with subquery)
        $teamMembersCount = (clone $accessibleProjects)
            ->where('status', ProjectStatus::ACTIVE)
            ->join('project_members', 'projects.id', '=', 'project_members.project_id')
            ->distinct('project_members.user_id')
            ->count('project_members.user_id');

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

        // Project progress (max 5 for display)
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

        $totalTasksCount = (int) ($taskStats->total_count ?? 0);
        $activeTasksCount = (int) ($taskStats->active_count ?? 0);
        $completedTasksCount = (int) ($taskStats->completed_count ?? 0);
        $reviewTasksCount = (int) ($taskStats->review_count ?? 0);
        $overdueCount = (int) ($taskStats->overdue_count ?? 0);
        $urgentCount = (int) ($taskStats->urgent_count ?? 0);
        $dueThisWeekCount = (int) ($taskStats->due_this_week_count ?? 0);
        $myTasksCount = (int) ($taskStats->my_tasks_count ?? 0);

        $onTimeTasks = max(0, $activeTasksCount - $overdueCount);
        $onTimeRate = $activeTasksCount > 0 ? (int) round(($onTimeTasks / $activeTasksCount) * 100) : 100;

        $stats = [
            'projects_count' => $activeProjectsCount,
            'my_tasks_count' => $myTasksCount,
            'active_tasks_count' => $activeTasksCount,
            'overdue_count' => $overdueCount,
            'team_members_count' => $teamMembersCount,
            'notifications_unread' => $user->can('notifications.view')
                ? $user->unreadNotifications()->count()
                : 0,
        ];

        $focusMetrics = [
            'on_time_rate' => $onTimeRate,
            'urgent_tasks_count' => $urgentCount,
            'review_tasks_count' => $reviewTasksCount,
            'due_this_week_count' => $dueThisWeekCount,
            'completed_tasks_count' => $completedTasksCount,
            'total_tasks_count' => $totalTasksCount,
            'completion_rate' => $totalTasksCount > 0
                ? (int) round(($completedTasksCount / $totalTasksCount) * 100)
                : 0,
            'my_tasks_count' => $myTasksCount,
            'overdue_count' => $overdueCount,
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'focus_metrics' => $focusMetrics,
            'tasks_by_status' => $tasksByStatus,
            'upcoming_deadlines' => $upcomingDeadlines,
            'recent_activities' => $recentActivities,
            'project_progress' => $projectProgress,
        ]);
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

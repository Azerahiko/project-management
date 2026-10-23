<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property int|null $assignee_id
 * @property int $creator_id
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read User $assignee
 * @property-read User $creator
 * @property bool|null $can_update
 * @property bool|null $can_delete
 */
#[Fillable([
    'project_id',
    'title',
    'description',
    'status',
    'priority',
    'assignee_id',
    'creator_id',
    'start_date',
    'due_date',
    'completed_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'project_id' => 'integer',
            'assignee_id' => 'integer',
            'creator_id' => 'integer',
        ];
    }

    /**
     * Get the project that owns the task.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user assigned to the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the user who created the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Scope to filter tasks accessible by a user.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->hasRole('administrator')) {
            return $query;
        }

        return $query->whereHas('project', function ($q) use ($user) {
            $q->accessibleBy($user);
        });
    }

    /**
     * Scope for search.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope for status filter.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeStatus($query, ?string $status)
    {
        return $query->when($status, fn ($q) => $q->where('status', $status));
    }

    /**
     * Scope for priority filter.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopePriority($query, ?string $priority)
    {
        return $query->when($priority, fn ($q) => $q->where('priority', $priority));
    }

    /**
     * Scope for assignee filter.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeAssignee($query, ?int $assigneeId)
    {
        return $query->when($assigneeId, fn ($q) => $q->where('assignee_id', $assigneeId));
    }

    /**
     * Scope for project filter.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeProject($query, ?int $projectId)
    {
        return $query->when($projectId, fn ($q) => $q->where('project_id', $projectId));
    }

    /**
     * Scope for sorting.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeSort($query, string $sortBy, string $direction = 'asc')
    {
        $allowedSorts = ['title', 'status', 'priority', 'start_date', 'due_date', 'created_at', 'updated_at'];

        return $query->orderBy(
            in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at',
            $direction === 'desc' ? 'desc' : 'asc'
        );
    }

    /**
     * Check if task is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status === TaskStatus::DONE) {
            return false;
        }

        return $this->due_date !== null && $this->due_date->isPast();
    }

    /**
     * Mark task as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => TaskStatus::DONE,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    /**
     * Get status color.
     */
    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    /**
     * Get priority label.
     */
    public function getPriorityLabelAttribute(): string
    {
        return $this->priority->label();
    }

    /**
     * Get priority color.
     */
    public function getPriorityColorAttribute(): string
    {
        return $this->priority->color();
    }
}

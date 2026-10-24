<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property ProjectPriority $priority
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, ProjectMember> $projectMembers
 * @property-read int|null $members_count
 * @property-read int|null $tasks_count
 * @property-read int|null $completed_tasks_count
 * @property bool|null $can_update
 * @property bool|null $can_delete
 * @property bool|null $can_manage_members
 */
#[Fillable(['name', 'description', 'status', 'priority', 'start_date', 'end_date', 'owner_id'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => ProjectPriority::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'owner_id' => 'integer',
        ];
    }

    /**
     * Get the owner (project manager) of the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all members of the project (through pivot).
     *
     * @return BelongsToMany<User, $this, ProjectMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps()
            ->using(ProjectMember::class);
    }

    /**
     * Get the project members pivot records.
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Get all tasks for the project.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Check if a user is a member of this project.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Get the role of a user in this project.
     */
    public function getMemberRole(User $user): ?ProjectRole
    {
        return $this->projectMembers()->where('user_id', $user->id)->first()?->role;
    }

    /**
     * Scope to filter projects accessible by a user.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->hasRole('administrator')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id));
        });
    }

    /**
     * Scope for search.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope for status filter.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeStatus($query, ?string $status)
    {
        return $query->when($status, fn ($q) => $q->where('status', $status));
    }

    /**
     * Scope for priority filter.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopePriority($query, ?string $priority)
    {
        return $query->when($priority, fn ($q) => $q->where('priority', $priority));
    }

    /**
     * Scope for owner filter.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOwner($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId));
    }

    /**
     * Scope with member count.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithMemberCount($query)
    {
        return $query->withCount('members');
    }
}

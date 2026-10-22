<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property ProjectRole $role
 * @property Carbon $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $user
 */
#[Fillable(['project_id', 'user_id', 'role', 'joined_at'])]
class ProjectMember extends Pivot
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    protected $table = 'project_members';

    public $incrementing = true;

    /**
     * Ensure the join date is always populated.
     */
    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            $member->joined_at ??= Carbon::now();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * Get the project that owns the member record.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user that owns the member record.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this member is the project owner.
     */
    public function isOwner(): bool
    {
        return $this->role === ProjectRole::OWNER;
    }
}

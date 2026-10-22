<?php

namespace App\Models;

use App\Enums\ProjectRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $avatar
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Project> $ownedProjects
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, ProjectMember> $projectMemberships
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the two-letter initials used as the avatar fallback.
     *
     * @return Attribute<uppercase-string, never>
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $words = preg_split('/\s+/', trim($this->name)) ?: [];

            return Str::upper(Str::substr($words[0] ?? '', 0, 1).Str::substr($words[1] ?? '', 0, 1));
        });
    }

    /**
     * Get the projects owned by this user (as project manager).
     *
     * @return HasMany<Project, $this>
     */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /**
     * Get all projects this user is a member of.
     *
     * @return BelongsToMany<Project, $this, ProjectMember>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps()
            ->using(ProjectMember::class);
    }

    /**
     * Get the project membership pivot records.
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Check if user is a member of a project.
     */
    public function isMemberOf(Project $project): bool
    {
        return $this->projects()->where('projects.id', $project->id)->exists();
    }

    /**
     * Get the role of this user in a project.
     */
    public function getProjectRole(Project $project): ?ProjectRole
    {
        return $this->projectMemberships()->where('project_id', $project->id)->first()?->role;
    }

    /**
     * Check if user is owner of a project.
     */
    public function isOwnerOf(Project $project): bool
    {
        return $this->getProjectRole($project) === ProjectRole::OWNER;
    }

    /**
     * Check if user can manage a project (owner or manager role).
     */
    public function canManageProject(Project $project): bool
    {
        $role = $this->getProjectRole($project);

        return $role === ProjectRole::OWNER || $role === ProjectRole::MANAGER;
    }
}

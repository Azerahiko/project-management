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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $avatar
 * @property array<string, bool>|null $notification_preferences
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $activation_code_hash
 * @property Carbon|null $activation_code_expires_at
 * @property int $activation_code_attempts
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $onboarding_password_changed_at
 * @property Carbon|null $onboarding_completed_at
 * @property-read Collection<int, Project> $ownedProjects
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, ProjectMember> $projectMemberships
 * @property-read Collection<int, Webhook> $webhooks
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'avatar', 'notification_preferences', 'onboarding_password_changed_at', 'onboarding_completed_at', 'email_verified_at', 'activation_code_hash', 'activation_code_expires_at', 'activation_code_attempts'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'notification_preferences' => 'array',
            'onboarding_password_changed_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'activation_code_expires_at' => 'datetime',
        ];
    }

    /**
     * Default notification preferences for users who have not customised them.
     *
     * @return array<string, bool>
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'task_updates' => true,
            'deadline_reminders' => true,
            'daily_digest' => false,
            'mentions' => true,
            'comments' => false,
            'sound' => false,
        ];
    }

    /**
     * Get notification preferences merged with the application defaults.
     *
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        return array_merge(
            self::defaultNotificationPreferences(),
            $this->notification_preferences ?? [],
        );
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
     * Get the outbound webhooks registered by this user.
     *
     * @return HasMany<Webhook, $this>
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
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

    /**
     * Get the invitation linked to this user by email.
     *
     * @return HasOne<Invitation, $this>
     */
    public function invitation(): HasOne
    {
        return $this->hasOne(Invitation::class, 'email', 'email');
    }

    /**
     * Get the current onboarding step.
     */
    public function onboardingStep(): string
    {
        if ($this->onboarding_completed_at !== null) {
            return 'complete';
        }

        if ($this->onboarding_password_changed_at === null) {
            return 'password';
        }

        if ($this->two_factor_confirmed_at === null) {
            return 'two_factor';
        }

        return 'passkey';
    }

    /**
     * Check if user has completed onboarding.
     */
    public function isOnboarded(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /**
     * Check if user needs to change password as part of onboarding.
     */
    public function needsOnboardingPasswordChange(): bool
    {
        return $this->onboarding_password_changed_at === null;
    }

    /**
     * Check if user needs to enable two-factor authentication.
     */
    public function needsTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at === null;
    }

    /**
     * Check if user needs to complete onboarding (any step).
     */
    public function needsOnboarding(): bool
    {
        return ! $this->isOnboarded();
    }

    /**
     * Increment the activation code attempts counter.
     */
    public function incrementActivationAttempts(): void
    {
        $this->increment('activation_code_attempts');
    }

    /**
     * Clear the activation code data (permanently invalidate).
     */
    public function clearActivationCode(): void
    {
        $this->forceFill([
            'activation_code_hash' => null,
            'activation_code_expires_at' => null,
            'activation_code_attempts' => 0,
        ])->save();
    }

    /**
     * Check if the user has a valid activation code.
     */
    public function hasValidActivationCode(): bool
    {
        return $this->activation_code_hash !== null
            && $this->activation_code_expires_at !== null
            && $this->activation_code_expires_at->isFuture();
    }
}

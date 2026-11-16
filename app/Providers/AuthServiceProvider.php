<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Webhook;
use App\Policies\ProjectPolicy;
use App\Policies\TaskCommentPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Policies\WebhookPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Project::class => ProjectPolicy::class,
        Task::class => TaskPolicy::class,
        TaskComment::class => TaskCommentPolicy::class,
        Webhook::class => WebhookPolicy::class,
    ];
}

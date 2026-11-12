<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProjectMemberRequest;
use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Requests\Api\UpdateProjectRequest;
use App\Http\Resources\ProjectMemberResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\ProjectMemberAdded;
use App\Notifications\ProjectOwnershipTransferred;
use App\Services\NotificationSender;
use App\Services\WebhookDispatcher;
use App\Support\Pagination;
use App\Support\Roles;
use App\Support\WebhookEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function __construct(
        protected NotificationSender $notificationSender,
        protected WebhookDispatcher $webhookDispatcher,
    ) {}

    /**
     * Display a paginated listing of projects.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->with(['owner', 'projectMembers.user'])
            ->withCount(['members', 'tasks'])
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
            ->when($request->has('owner_id'), function ($query) use ($request) {
                $query->owner($request->integer('owner_id'));
            })
            ->when($request->string('sort_by')->isNotEmpty(), function ($query) use ($request) {
                $sortBy = $request->string('sort_by')->toString();
                $direction = $request->string('sort_direction')->toString() === 'desc' ? 'desc' : 'asc';

                $allowedSorts = ['name', 'status', 'priority', 'start_date', 'end_date', 'created_at'];
                $query->orderBy(in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at', $direction);
            }, fn ($query) => $query->latest())
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        // Add authorization flags to each project
        $projects->getCollection()->transform(function ($project) use ($request) {
            $project->can_update = $request->user()?->can('update', $project) ?? false;
            $project->can_delete = $request->user()?->can('delete', $project) ?? false;
            $project->can_manage_members = $request->user()?->can('manageMembers', $project) ?? false;

            return $project;
        });

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Ensure owner_id is set
            if (! isset($data['owner_id'])) {
                $data['owner_id'] = $request->user()->id;
            }

            $project = Project::create($data);

            // Add owner as project member with OWNER role
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $data['owner_id'],
                'role' => ProjectRole::OWNER,
                'joined_at' => now(),
            ]);

            return $project->load(['owner', 'projectMembers.user']);
        });

        $this->webhookDispatcher->dispatch(WebhookEvents::PROJECT_CREATED, [
            'project_id' => $project->id,
            'name' => $project->name,
            'status' => $project->status->value,
            'priority' => $project->priority->value,
            'owner' => [
                'id' => $project->owner->id,
                'name' => $project->owner->name,
            ],
            'created_by' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
        ]);

        return ProjectResource::make($project)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        $project->load(['owner', 'projectMembers.user']);
        $project->can_update = $request->user()?->can('update', $project) ?? false;
        $project->can_delete = $request->user()?->can('delete', $project) ?? false;
        $project->can_manage_members = $request->user()?->can('manageMembers', $project) ?? false;

        return ProjectResource::make($project);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $data = $request->validated();
        $newOwnerId = isset($data['owner_id']) ? (int) $data['owner_id'] : $project->owner_id;
        $ownerChanged = $newOwnerId !== $project->owner_id;
        $previousStatus = $project->status->value;

        // Only the current owner or an administrator may transfer ownership.
        if ($ownerChanged
            && ! $request->user()->hasRole('administrator')
            && $project->owner_id !== $request->user()->id) {
            abort(403, 'Hanya pemilik proyek atau administrator yang dapat mengubah pemilik proyek.');
        }

        // Validate new owner is a member of the project (or admin)
        if ($ownerChanged) {
            $newOwner = User::findOrFail($newOwnerId);
            if (! $newOwner->is_active) {
                abort(422, 'Pengguna yang dipilih tidak aktif.');
            }
            if (! $project->hasMember($newOwner) && ! $newOwner->hasRole('administrator')) {
                abort(422, 'Pemilik baru harus menjadi anggota proyek.');
            }
        }

        DB::transaction(function () use ($project, $data, $newOwnerId, $ownerChanged, $request) {
            $previousOwnerId = $project->owner_id;

            $project->fill($data);
            $project->save();

            if ($ownerChanged) {
                $this->syncProjectOwner($project, $newOwnerId, $previousOwnerId, $request->user());
            }
        });

        $project->refresh();

        if ($previousStatus !== $project->status->value && $project->status->value === 'completed') {
            $this->webhookDispatcher->dispatch(WebhookEvents::PROJECT_COMPLETED, [
                'project_id' => $project->id,
                'name' => $project->name,
                'previous_status' => $previousStatus,
                'new_status' => $project->status->value,
                'completed_by' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
            ]);
        }

        // Dispatch ownership transfer webhook and notifications after commit
        if ($ownerChanged) {
            $previousOwnerId = $project->getOriginal('owner_id');
            $previousOwner = User::find($previousOwnerId);
            $newOwner = User::find($newOwnerId);
            $transferredBy = $request->user();

            DB::afterCommit(function () use ($project, $previousOwnerId, $newOwnerId, $transferredBy) {
                $previousOwner = User::query()->where('id', $previousOwnerId)->first();
                $newOwner = User::query()->where('id', $newOwnerId)->first();

                // Webhook
                $this->webhookDispatcher->dispatch(WebhookEvents::PROJECT_OWNERSHIP_TRANSFERRED, [
                    'project_id' => $project->id,
                    'name' => $project->name,
                    'previous_owner' => $previousOwner ? [
                        'id' => $previousOwner->id,
                        'name' => $previousOwner->name,
                        'email' => $previousOwner->email,
                    ] : null,
                    'new_owner' => $newOwner ? [
                        'id' => $newOwner->id,
                        'name' => $newOwner->name,
                        'email' => $newOwner->email,
                    ] : null,
                    'transferred_by' => [
                        'id' => $transferredBy->id,
                        'name' => $transferredBy->name,
                    ],
                ]);

                // Notify previous owner (they lost ownership)
                if ($previousOwner instanceof User) {
                    $this->notificationSender->send($previousOwner, ProjectOwnershipTransferred::class, [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'previous_owner_name' => $previousOwner->name,
                        'new_owner_name' => $newOwner instanceof User ? $newOwner->name : '',
                        'transferred_by_name' => $transferredBy->name,
                        'role' => 'previous_owner',
                    ]);
                }

                // Notify new owner (they gained ownership)
                if ($newOwner instanceof User) {
                    $this->notificationSender->send($newOwner, ProjectOwnershipTransferred::class, [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'previous_owner_name' => $previousOwner instanceof User ? $previousOwner->name : '',
                        'new_owner_name' => $newOwner->name,
                        'transferred_by_name' => $transferredBy->name,
                        'role' => 'new_owner',
                    ]);
                }
            });
        }

        return ProjectResource::make($project->load(['owner', 'projectMembers.user']));
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        DB::transaction(function () use ($project) {
            $project->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * List members of a project.
     */
    public function members(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $members = $project->projectMembers()
            ->with('user')
            ->orderBy('role', 'desc')
            ->orderBy('joined_at')
            ->get();

        return ProjectMemberResource::collection($members);
    }

    /**
     * Add a member to a project.
     */
    public function addMember(ProjectMemberRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $validated = $request->validated();

        $member = DB::transaction(function () use ($project, $validated) {
            // Check if user is already a member
            $user = User::findOrFail((int) $validated['user_id']);
            if ($project->hasMember($user)) {
                abort(422, 'Pengguna sudah menjadi anggota proyek ini.');
            }

            return ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $validated['user_id'],
                'role' => $validated['role'],
                'joined_at' => now(),
            ])->load('user');
        });

        // Send ProjectMemberAdded notification to the new member
        $user = $member->user;
        DB::afterCommit(function () use ($project, $user, $validated, $request) {
            $this->notificationSender->send($user, ProjectMemberAdded::class, [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'role' => $validated['role'],
                'added_by_name' => $request->user()->name,
                'action' => 'project_member_added',
            ]);
        });

        return ProjectMemberResource::make($member)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a member's role in a project.
     */
    public function updateMember(ProjectMemberRequest $request, Project $project, User $user): ProjectMemberResource
    {
        $this->authorize('manageMembers', $project);

        // Cannot change owner's role
        if ($project->owner_id === $user->id) {
            abort(422, 'Tidak dapat mengubah peran pemilik proyek.');
        }

        $member = $project->projectMembers()->where('user_id', $user->id)->firstOrFail();

        $member->update([
            'role' => $request->validated('role'),
        ]);

        return ProjectMemberResource::make($member->load('user'));
    }

    /**
     * Remove a member from a project.
     */
    public function removeMember(Request $request, Project $project, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        // Cannot remove owner
        if ($project->owner_id === $user->id) {
            abort(422, 'Tidak dapat menghapus pemilik proyek.');
        }

        $member = $project->projectMembers()->where('user_id', $user->id)->firstOrFail();

        DB::transaction(function () use ($member) {
            $member->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * List active users who can still be added to the project.
     */
    public function availableUsers(Request $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $perPage = min(max((int) $request->query('per_page', 20), 5), 50);
        $search = $request->query('search', '');

        $query = User::query()
            ->where('is_active', true)
            ->whereDoesntHave('projectMemberships', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            })
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'data' => $users->getCollection()->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'initials' => $user->initials,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * List users assignable as project owner.
     *
     * Global candidates: administrator, project_manager, team_leader.
     * When `project` is provided, that project's owner and manager members
     * are included as well.
     */
    public function managers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'integer', 'exists:projects,id'],
        ]);

        $projectId = $validated['project'] ?? null;

        $managers = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($projectId): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', [
                    Roles::ADMINISTRATOR,
                    Roles::PROJECT_MANAGER,
                    Roles::TEAM_LEADER,
                ]));

                if ($projectId === null) {
                    return;
                }

                $query->orWhereHas('projectMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('project_id', $projectId)
                    ->whereIn('role', [ProjectRole::OWNER->value, ProjectRole::MANAGER->value]));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar']);

        return response()->json([
            'data' => $managers->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'initials' => $user->initials,
            ]),
        ]);
    }

    /**
     * Keep the project member pivot in sync when ownership changes.
     */
    private function syncProjectOwner(Project $project, int $newOwnerId, int $previousOwnerId, ?User $transferredBy): void
    {
        $project->projectMembers()
            ->where('user_id', $previousOwnerId)
            ->update(['role' => ProjectRole::MANAGER]);

        $project->projectMembers()->updateOrCreate(
            ['user_id' => $newOwnerId],
            ['role' => ProjectRole::OWNER]
        );

        // Log ownership transfer activity
        ProjectActivity::create([
            'project_id' => $project->id,
            'user_id' => $transferredBy?->id,
            'activity_type' => 'project.ownership_transferred',
            'field' => 'owner_id',
            'old_value' => (string) $previousOwnerId,
            'new_value' => (string) $newOwnerId,
            'description' => ($transferredBy->name ?? 'Sistem')." mentransfer pemilik proyek dari pengguna {$previousOwnerId} ke {$newOwnerId}.",
        ]);
    }

    /**
     * List soft-deleted projects.
     */
    public function trashed(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();
        $perPage = $request->integer('per_page', 15);
        $projects = Project::onlyTrashed()
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('projectMembers', fn ($q) => $q->where('user_id', $user->id));
            })
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $query->search($request->string('search')->toString());
            })
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request) {
                $query->status($request->string('status')->toString());
            })
            ->when($request->string('priority')->isNotEmpty(), function ($query) use ($request) {
                $query->priority($request->string('priority')->toString());
            })
            ->when($request->string('sort_by')->isNotEmpty(), function ($query) use ($request) {
                $sortBy = $request->string('sort_by')->toString();
                $direction = $request->string('sort_direction')->toString() === 'desc' ? 'desc' : 'asc';

                $allowedSorts = ['name', 'status', 'priority', 'start_date', 'end_date', 'created_at', 'deleted_at'];
                $query->orderBy(in_array($sortBy, $allowedSorts, true) ? $sortBy : 'deleted_at', $direction);
            }, fn ($query) => $query->latest('deleted_at'))
            ->with(['owner', 'projectMembers.user'])
            ->withCount(['members', 'tasks'])
            ->paginate($perPage)
            ->withQueryString();

        $projects->getCollection()->transform(function ($project) use ($request) {
            $project->can_update = $request->user()?->can('update', $project) ?? false;
            $project->can_delete = $request->user()?->can('delete', $project) ?? false;

            return $project;
        });

        return ProjectResource::collection($projects);
    }

    /**
     * Restore a soft-deleted project.
     */
    public function restore(Request $request, Project $project): JsonResponse
    {
        $this->authorize('restore', $project);

        $project->restore();

        return response()->json(['message' => 'Proyek berhasil dipulihkan.']);
    }
}

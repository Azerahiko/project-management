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
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a paginated listing of projects.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->with(['owner', 'members'])
            ->withCount('members')
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
            ->paginate((int) $request->integer('per_page', 15))
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

            return $project->load(['owner', 'members']);
        });

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

        $project->fill($request->validated());
        $project->save();

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
     * List available project managers (users with project_manager role or administrator).
     */
    public function managers(): JsonResponse
    {
        $managers = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['administrator', 'project_manager']);
            })
            ->where('is_active', true)
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
}

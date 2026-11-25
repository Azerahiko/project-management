<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserUpdated;
use App\Services\InvitationService;
use App\Services\NotificationSender;
use App\Support\Pagination;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a paginated listing of users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['roles', 'invitation'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('role')->isNotEmpty(), function ($query) use ($request) {
                $query->whereHas('roles', fn ($query) => $query->where('name', $request->string('role')->toString()));
            })
            ->when($request->has('is_active') && $request->string('is_active')->toString() !== '', function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->string('sort_by')->isNotEmpty(), function ($query) use ($request) {
                $sortBy = $request->string('sort_by')->toString();
                $direction = $request->string('sort_direction')->toString() === 'desc' ? 'desc' : 'asc';

                $query->orderBy(in_array($sortBy, ['name', 'email', 'created_at'], true) ? $sortBy : 'created_at', $direction);
            }, fn ($query) => $query->latest())
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Generate a temporary password for user creation.
     *
     * Guarantees at least one character from each required class:
     * uppercase, lowercase, digits, and symbols, using random_int for
     * cryptographic security, then shuffles the result.
     */
    protected function generateSecureTemporaryPassword(int $length = 20): string
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $symbols = '!@#$%^&*()-_=+[]{}|;:,.<>?/';

        // Pick one random character from each class
        $password = $uppercase[random_int(0, strlen($uppercase) - 1)]
                  .$lowercase[random_int(0, strlen($lowercase) - 1)]
                  .$digits[random_int(0, strlen($digits) - 1)]
                  .$symbols[random_int(0, strlen($symbols) - 1)];

        // Fill remaining characters from combined pool
        $pool = $uppercase.$lowercase.$digits.$symbols;
        while (strlen($password) < $length) {
            $password .= $pool[random_int(0, strlen($pool) - 1)];
        }

        // Shuffle cryptographically using random_int
        $passwordArray = str_split($password);
        for ($i = count($passwordArray) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $passwordArray[$i];
            $passwordArray[$i] = $passwordArray[$j];
            $passwordArray[$j] = $tmp;
        }

        return implode('', $passwordArray);
    }

    /**
     * Generate a temporary password for user creation.
     */
    public function generateTemporaryPassword(Request $request): JsonResponse
    {
        $this->authorize('users.create');

        $temporaryPassword = $this->generateSecureTemporaryPassword(20);

        return response()->json([
            'temporary_password' => $temporaryPassword,
            'message' => 'Temporary password generated successfully',
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request, InvitationService $invitations): JsonResponse
    {
        $data = $request->safe()->only(['name', 'email', 'role', 'is_active', 'avatar']);
        $result = $invitations->create(
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'is_active' => $data['is_active'] ?? true,
                'avatar' => $data['avatar'] ?? null,
            ],
            $request->validated('temporary_password'),
            $request->user(),
        );

        return response()->json([
            'data' => UserResource::make($result['user']->load(['roles', 'invitation'])),
        ], 201);
    }

    /**
     * Display the given user.
     */
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return UserResource::make($user->load('roles'));
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);
        $this->ensureLastActiveAdministratorIsPreserved($request, $user);

        // Capture original values BEFORE any changes
        $originalName = $user->name;
        $originalEmail = Str::lower($user->email);
        $originalIsActive = (bool) $user->is_active;
        $originalRole = $user->roles()->value('name');

        DB::transaction(function () use ($request, $user) {
            $data = $request->safe()->only(['name', 'email', 'avatar']);

            if ($request->has('is_active')) {
                $data['is_active'] = $request->boolean('is_active');
            }

            if ($request->filled('password')) {
                $data['password'] = $request->validated('password');
            }

            $user->fill($data);

            // Never blank out an avatar when the field is omitted.
            if (! $request->has('avatar')) {
                $user->avatar = $user->getOriginal('avatar');
            }

            $user->save();

            if ($request->has('role')) {
                $user->syncRoles($this->assignableRoles($request, [$request->validated('role')]));
            }
        });

        // Deteksi field yang sebenarnya berubah sesudah save & syncRoles
        $changedFields = $this->detectChangedFields(
            $originalName,
            $originalEmail,
            $originalIsActive,
            $originalRole,
            $request->input('name'),
            $request->input('email'),
            $request->boolean('is_active'),
            $request->input('role'),
            ! empty($request->validated('password'))
        );

        // Kirim notifikasi ke user yang diedit (kecuali admin sendiri)
        if (! empty($changedFields) && $user->id !== $request->user()->id) {
            $sender = app(NotificationSender::class);
            $sender->send(
                $user,
                UserUpdated::class,
                [
                    'changed_fields' => implode(', ', $changedFields),
                    'updated_by_name' => $request->user()->name,
                ],
            );
        }

        $emailChanged = Str::lower($user->email) !== $originalEmail;
        if ($emailChanged && $user->id !== $request->user()->id) {
            $this->logoutUserOnEmailChange($user);
        }

        return UserResource::make($user->fresh('roles'));
    }

    /**
     * Logout user by invalidating session and rotating remember token.
     * Panggil ketika admin mengubah email user lain.
     */
    protected function logoutUserOnEmailChange(User $user): void
    {
        // Hapus seluruh sesi aktif user
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        // Rotasi remember token
        $user->setRememberToken(Str::random(60));
        $user->saveQuietly();

        // Hapus token API (Sanctum)
        $user->tokens()->delete();
    }

    /**
     * Deteksi field yang sebenarnya berubah.
     *
     * @return array<int, string>
     */
    protected function detectChangedFields(string $originalName,
        string $originalEmail,
        bool $originalIsActive,
        ?string $originalRole,
        ?string $newName,
        ?string $newEmail,
        bool $newIsActive,
        ?string $newRole,
        bool $passwordChanged,
    ): array {
        $fields = [];

        if ($newName !== null && $newName !== $originalName) {
            $fields[] = 'Nama';
        }

        if ($newEmail !== null && Str::lower($newEmail) !== $originalEmail) {
            $fields[] = 'Email';
        }

        if ($newIsActive !== $originalIsActive) {
            $fields[] = 'Status Akun';
        }

        if ($newRole !== null && $newRole !== $originalRole) {
            $fields[] = 'Peran';
        }

        if ($passwordChanged) {
            $fields[] = 'Kata Sandi';
        }

        return $fields;
    }

    /**
     * Remove the given user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->ownedProjects()->withTrashed()->exists()) {
            abort(422, 'Pindahkan kepemilikan proyek pengguna ini sebelum menghapus akun.');
        }

        DB::transaction(function () use ($user) {
            Invitation::where('email', $user->email)->delete();
            $user->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * List the available roles.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::query()->orderBy('name')->get();

        return response()->json([
            'data' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => Roles::all()[$role->name] ?? $role->name,
            ])->values(),
        ]);
    }

    /**
     * Restrict the roles an administrator is allowed to assign.
     *
     * @param  list<string>  $roles
     * @return list<string>
     */
    protected function assignableRoles(Request $request, array $roles): array
    {
        return array_values(array_intersect($roles, Roles::assignableBy($request->user())));
    }

    /**
     * Prevent deactivating or demoting the last active administrator.
     */
    protected function ensureLastActiveAdministratorIsPreserved(UpdateUserRequest $request, User $user): void
    {
        if (! $user->is_active || ! $user->hasRole(Roles::ADMINISTRATOR)) {
            return;
        }

        $willBeDeactivated = $request->has('is_active') && ! $request->boolean('is_active');
        $willBeDemoted = $request->filled('role') && $request->validated('role') !== Roles::ADMINISTRATOR;

        if (! $willBeDeactivated && ! $willBeDemoted) {
            return;
        }

        $otherActiveAdministrators = User::role(Roles::ADMINISTRATOR)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($otherActiveAdministrators === 0) {
            abort(403, 'Tidak dapat menonaktifkan atau menurunkan peran administrator aktif terakhir.');
        }
    }
}

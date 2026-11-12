<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use App\Services\WebhookUrlValidator;
use App\Support\Permissions;
use App\Support\WebhookEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

class ApiIntegrationController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
    ) {}

    /**
     * Display the API, token, and webhook integration hub.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(Permissions::SETTINGS_VIEW);

        $user = $request->user();

        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $user->tokens()->orderByDesc('created_at')->get();

        $tokenData = $tokens->map(fn (PersonalAccessToken $token): array => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => $token->last_used_at?->diffForHumans(),
            'last_used_at_iso' => $token->last_used_at?->toISOString(),
            'expires_at' => $token->expires_at?->translatedFormat('d M Y, H:i'),
            'expires_at_iso' => $token->expires_at?->toISOString(),
            'is_expired' => $token->expires_at?->isPast() ?? false,
            'created_at' => $token->created_at?->translatedFormat('d M Y'),
        ]);

        $perPage = (int) $request->query('webhooks_per_page', 15);
        $perPage = min(max($perPage, 5), 50); // Clamp between 5 and 50

        $webhooksPaginator = Webhook::query()
            ->with('user:id,name')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $canManageWebhooks = $user->can(Permissions::ROLES_MANAGE);
        $canViewWebhookSecrets = $user->can(Permissions::WEBHOOKS_VIEW_SECRETS);
        $flashedSecret = $canViewWebhookSecrets ? $request->session()->get('new_webhook_secret') : null;

        $activeTokens = $tokenData->reject(fn (array $token): bool => $token['is_expired']);
        $webhooks = $webhooksPaginator->getCollection();
        $activeWebhooks = $webhooks->where('is_active', true);
        $deliveredWebhooks = $webhooks->whereNotNull('last_delivery_status');
        $successfulWebhooks = $webhooks->where('last_delivery_status', 'success');

        return Inertia::render('Integrations/Index', [
            'tokens' => $tokenData,
            'new_token' => $request->session()->get('new_token'),
            'new_webhook_secret' => $flashedSecret,
            'base_url' => $request->getSchemeAndHttpHost().'/api',
            'available_scopes' => [
                '*' => [
                    'label' => 'Akses Penuh (*)',
                    'description' => 'Akses penuh ke semua endpoint proyek, tugas, komentar, dan anggota.',
                ],
                'projects:read' => [
                    'label' => 'Lihat Proyek',
                    'description' => 'Membaca data daftar proyek, anggota tim, dan statistik proyek.',
                ],
                'projects:write' => [
                    'label' => 'Kelola Proyek',
                    'description' => 'Membuat, memperbarui status, dan mengarsipkan proyek.',
                ],
                'tasks:read' => [
                    'label' => 'Lihat Tugas',
                    'description' => 'Membaca daftar tugas, prioritas, tenggat waktu, dan komentar.',
                ],
                'tasks:write' => [
                    'label' => 'Kelola Tugas',
                    'description' => 'Membuat tugas baru, memperbarui progres, dan menghapus tugas.',
                ],
                'users:read' => [
                    'label' => 'Lihat Pengguna & Tim',
                    'description' => 'Membaca daftar nama, email, dan peran anggota tim.',
                ],
                'users:write' => [
                    'label' => 'Kelola Pengguna',
                    'description' => 'Membuat, memperbarui, dan menghapus pengguna serta mengundang anggota baru.',
                ],
                'notifications:read' => [
                    'label' => 'Lihat Notifikasi',
                    'description' => 'Membaca dan menandai notifikasi sebagai sudah dibaca.',
                ],
            ],
            'endpoints_summary' => $this->endpointSummaries(),
            'webhooks' => $webhooksPaginator->through(fn (Webhook $webhook): array => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'secret' => null, // Secret only shown once via flashed session on create
                'events' => $webhook->events ?? [],
                'is_active' => $webhook->is_active,
                'last_delivery_status' => $webhook->last_delivery_status,
                'last_delivery_code' => $webhook->last_delivery_code,
                'last_delivery_latency_ms' => $webhook->last_delivery_latency_ms,
                'last_delivery_error' => $webhook->last_delivery_error,
                'last_delivery_at' => $webhook->last_delivery_at?->diffForHumans(),
                'last_delivery_at_iso' => $webhook->last_delivery_at?->toISOString(),
                'created_by' => $webhook->user->name,
                'created_at' => $webhook->created_at?->translatedFormat('d M Y'),
            ]),
            'webhook_events' => WebhookEvents::all(),
            'can_manage_webhooks' => $canManageWebhooks,
            'can_view_webhook_secrets' => $canViewWebhookSecrets,
            'stats' => [
                'tokens_count' => $tokens->count(),
                'active_tokens_count' => $activeTokens->count(),
                'expired_tokens_count' => $tokens->count() - $activeTokens->count(),
                'webhooks_count' => $webhooksPaginator->total(),
                'active_webhooks_count' => $activeWebhooks->count(),
                'successful_deliveries_count' => $successfulWebhooks->count(),
                'failed_deliveries_count' => $deliveredWebhooks->where('last_delivery_status', 'failed')->count(),
                'delivery_success_rate' => $deliveredWebhooks->isEmpty()
                    ? null
                    : (int) round(($successfulWebhooks->count() / $deliveredWebhooks->count()) * 100),
            ],
        ]);
    }

    /**
     * Generate a new Personal Access Token.
     */
    public function storeToken(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::SETTINGS_VIEW);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in(array_keys($this->availableScopes()))],
            'expires_in_days' => ['nullable', 'integer', 'in:0,7,30,90,365'],
        ], [
            'name.required' => 'Nama token wajib diisi.',
            'name.max' => 'Nama token maksimal 100 karakter.',
            'abilities.*.in' => 'Cakupan izin token tidak valid.',
        ]);

        $requestedAbilities = array_values($validated['abilities'] ?? ['*']);
        if ($requestedAbilities === [] || in_array('*', $requestedAbilities, true)) {
            $requestedAbilities = ['*'];
        }

        // Validate requested abilities against user's actual permissions
        $authorizedAbilities = $this->filterAuthorizedAbilities($request->user(), $requestedAbilities);

        if ($authorizedAbilities !== $requestedAbilities) {
            $unauthorized = array_diff($requestedAbilities, $authorizedAbilities);

            return redirect()->back()
                ->with('error', 'Beberapa cakupan izin tidak diizinkan untuk peran Anda: '.implode(', ', $unauthorized))
                ->withInput();
        }

        $expiresInDays = (int) ($validated['expires_in_days'] ?? 0);
        $expiresAt = $expiresInDays > 0 ? Carbon::now()->addDays($expiresInDays) : null;

        $token = $request->user()->createToken(
            name: trim($validated['name']),
            abilities: $authorizedAbilities,
            expiresAt: $expiresAt,
        );

        return redirect()->back()
            ->with('new_token', [
                'name' => $validated['name'],
                'plain_text_token' => $token->plainTextToken,
                'expires_at' => $expiresAt?->translatedFormat('d M Y, H:i') ?? 'Tidak pernah kedaluwarsa',
            ])
            ->with('success', 'Kunci API berhasil dibuat. Salin kunci Anda sekarang.');
    }

    /**
     * Filter abilities to only include those the user is authorized for.
     *
     * @param  list<string>  $requestedAbilities
     * @return list<string>
     */
    private function filterAuthorizedAbilities(User $user, array $requestedAbilities): array
    {
        // Administrators can request any scope
        if ($user->hasRole('administrator')) {
            return $requestedAbilities;
        }

        $scopeToPermission = $this->scopeToPermissionMap();
        $authorized = [];

        foreach ($requestedAbilities as $ability) {
            // Wildcard requires all permissions
            if ($ability === '*') {
                // Only grant * if user has all the permissions it represents
                $requiredPermissions = array_unique(array_merge(...$scopeToPermission));
                if (empty(array_diff($requiredPermissions, $user->getAllPermissions()->pluck('name')->toArray()))) {
                    $authorized[] = '*';
                }

                continue;
            }

            if (isset($scopeToPermission[$ability])) {
                $requiredPermissions = $scopeToPermission[$ability];
                $hasAllPermissions = true;
                foreach ($requiredPermissions as $permission) {
                    if (! $user->can($permission)) {
                        $hasAllPermissions = false;
                        break;
                    }
                }
                if ($hasAllPermissions) {
                    $authorized[] = $ability;
                }
            }
        }

        return $authorized;
    }

    /**
     * Map API scopes to required Laravel permissions.
     *
     * @return array<string, list<string>>
     */
    private function scopeToPermissionMap(): array
    {
        return [
            'projects:read' => [Permissions::PROJECTS_VIEW],
            'projects:write' => [Permissions::PROJECTS_CREATE, Permissions::PROJECTS_UPDATE, Permissions::PROJECTS_DELETE],
            'tasks:read' => [Permissions::TASKS_VIEW],
            'tasks:write' => [Permissions::TASKS_CREATE, Permissions::TASKS_UPDATE, Permissions::TASKS_DELETE],
            'users:read' => [Permissions::USERS_VIEW],
            'users:write' => [Permissions::USERS_CREATE, Permissions::USERS_UPDATE, Permissions::USERS_DELETE],
            'notifications:read' => [Permissions::NOTIFICATIONS_VIEW],
        ];
    }

    /**
     * Revoke a Personal Access Token owned by the authenticated user.
     */
    public function destroyToken(Request $request, int $tokenId): RedirectResponse
    {
        Gate::authorize(Permissions::SETTINGS_VIEW);

        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();

        if (! $deleted) {
            return redirect()->back()
                ->with('error', 'Kunci API tidak ditemukan atau bukan milik Anda.');
        }

        return redirect()->back()->with('success', 'Kunci API berhasil dicabut.');
    }

    /**
     * Register a new outbound webhook.
     */
    public function storeWebhook(Request $request): RedirectResponse
    {
        Log::info('storeWebhook called', ['url' => $request->input('url')]);

        $this->authorizeWebhookManagement($request);

        $validated = $this->validateWebhook($request);

        $secret = Str::random(40);

        Webhook::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => $secret,
            'is_active' => true,
        ]);

        return redirect()->back()->with([
            'success' => 'Webhook berhasil didaftarkan.',
            'new_webhook_secret' => $secret,
        ]);
    }

    /**
     * Update an existing outbound webhook.
     */
    public function updateWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeWebhookManagement($request);

        $validated = $this->validateWebhook($request);

        $webhook->update([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? $webhook->is_active,
        ]);

        return redirect()->back()->with('success', 'Webhook berhasil diperbarui.');
    }

    /**
     * Toggle the active state of an outbound webhook.
     */
    public function toggleWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeWebhookManagement($request);

        $webhook->update(['is_active' => ! $webhook->is_active]);

        return redirect()->back()->with(
            'success',
            $webhook->is_active ? 'Webhook diaktifkan.' : 'Webhook dinonaktifkan.',
        );
    }

    /**
     * Remove an outbound webhook.
     */
    public function destroyWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeWebhookManagement($request);

        $webhook->delete();

        return redirect()->back()->with('success', 'Webhook berhasil dihapus.');
    }

    /**
     * Send a signed test payload to a webhook.
     */
    public function testWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeWebhookManagement($request);

        $result = $this->webhookDispatcher->sendSync($webhook, 'webhook.test', [
            'message' => 'Ini adalah payload uji dari DigitalCode Tasks Flow.',
            'webhook_name' => $webhook->name,
            'triggered_by' => $request->user()->name,
        ]);

        if (! $result['success']) {
            return redirect()->back()->with(
                'error',
                'Uji webhook gagal'.($result['status'] ? " (HTTP {$result['status']})" : '').': '.($result['error'] ?? 'Tidak ada respon.'),
            );
        }

        return redirect()->back()->with(
            'success',
            "Uji webhook berhasil (HTTP {$result['status']}) dalam {$result['latency_ms']}ms.",
        );
    }

    private function authorizeWebhookManagement(Request $request): void
    {
        Gate::authorize(Permissions::ROLES_MANAGE);
    }

    /**
     * @return array{name: string, url: string, events: list<string>, is_active?: bool}
     */
    private function validateWebhook(Request $request): array
    {
        Log::info('validateWebhook called', ['url' => $request->input('url')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookEvents::slugs())],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama webhook wajib diisi.',
            'url.required' => 'URL tujuan webhook wajib diisi.',
            'url.url' => 'URL webhook harus menggunakan format http atau https yang valid.',
            'events.required' => 'Pilih minimal satu event webhook.',
            'events.min' => 'Pilih minimal satu event webhook.',
            'events.*.in' => 'Pilihan event webhook tidak valid.',
        ]);

        Log::info('Initial validation passed', ['url' => $validated['url']]);

        // SSRF protection: validate URL at creation/update time
        try {
            WebhookUrlValidator::validate($validated['url']);
        } catch (InvalidArgumentException $e) {
            // Add error to validator and throw
            Log::info('SSRF validation failed', [
                'url' => $validated['url'],
                'error' => $e->getMessage(),
            ]);
            $validator = Validator::make($validated, []);
            $validator->errors()->add('url', $e->getMessage());
            throw new ValidationException($validator);
        }

        return $validated;
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    private function availableScopes(): array
    {
        return [
            '*' => ['label' => 'Akses Penuh', 'description' => 'Akses ke semua endpoint.'],
            'projects:read' => ['label' => 'Lihat Proyek', 'description' => 'Membaca data proyek.'],
            'projects:write' => ['label' => 'Kelola Proyek', 'description' => 'Mengubah data proyek.'],
            'tasks:read' => ['label' => 'Lihat Tugas', 'description' => 'Membaca data tugas.'],
            'tasks:write' => ['label' => 'Kelola Tugas', 'description' => 'Mengubah data tugas.'],
            'users:read' => ['label' => 'Lihat Pengguna', 'description' => 'Membaca data anggota tim.'],
        ];
    }

    /**
     * Documented API endpoints surfaced in the integration console.
     *
     * @return list<array{method: string, path: string, description: string, sample_body: string, permission: string}>
     */
    private function endpointSummaries(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/projects',
                'description' => 'Daftar proyek yang dapat diakses token.',
                'sample_body' => '',
                'permission' => 'projects.view',
            ],
            [
                'method' => 'POST',
                'path' => '/projects',
                'description' => 'Membuat proyek baru di ruang kerja.',
                'sample_body' => json_encode([
                    'name' => 'Proyek Baru via API',
                    'description' => 'Dibuat langsung dari API Console',
                    'status' => 'active',
                    'priority' => 'medium',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
                'permission' => 'projects.create',
            ],
            [
                'method' => 'GET',
                'path' => '/tasks',
                'description' => 'Daftar tugas dengan filter status, prioritas, dan proyek.',
                'sample_body' => '',
                'permission' => 'tasks.view',
            ],
            [
                'method' => 'POST',
                'path' => '/tasks',
                'description' => 'Membuat tugas baru pada proyek tertentu.',
                'sample_body' => json_encode([
                    'project_id' => 1,
                    'title' => 'Tugas Baru via API',
                    'description' => 'Dibuat langsung dari API Console',
                    'status' => 'todo',
                    'priority' => 'medium',
                    'due_date' => now()->addWeek()->toDateString(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
                'permission' => 'tasks.view',
            ],
            [
                'method' => 'GET',
                'path' => '/users',
                'description' => 'Daftar anggota tim beserta peran aktif.',
                'sample_body' => '',
                'permission' => 'users.view',
            ],
            [
                'method' => 'GET',
                'path' => '/roles',
                'description' => 'Daftar peran sistem yang tersedia.',
                'sample_body' => '',
                'permission' => 'users.view',
            ],
        ];
    }
}

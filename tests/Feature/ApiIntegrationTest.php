<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('redirects guests attempting to access integrations page', function () {
    $this->get('/integrations')->assertRedirect('/login');
});

it('renders the integrations page for authenticated users', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');

    $this->actingAs($user)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Integrations/Index')
            ->has('tokens')
            ->has('available_scopes')
            ->has('endpoints_summary')
            ->where('base_url', url('/api'))
        );
});

it('allows an authenticated user to generate a personal access token', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');

    $response = $this->actingAs($user)->post('/integrations/tokens', [
        'name' => 'CI/CD Pipeline Runner',
        'abilities' => ['projects:read', 'tasks:read'],
        'expires_in_days' => 30,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('new_token');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'CI/CD Pipeline Runner',
    ]);

    $token = $user->tokens()->first();
    expect($token)->not->toBeNull();
    expect($token->abilities)->toEqual(['projects:read', 'tasks:read']);
    expect($token->expires_at)->not->toBeNull();
});

it('allows a user to revoke their own personal access token', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');

    $token = $user->createToken('Temporary Token');

    $this->actingAs($user)
        ->delete("/integrations/tokens/{$token->accessToken->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

it('prevents a user from revoking another user token', function () {
    $userA = User::factory()->create(['is_active' => true]);
    $userA->assignRole('member');

    $userB = User::factory()->create(['is_active' => true]);
    $userB->assignRole('member');

    $tokenB = $userB->createToken('User B Secret Token');

    $this->actingAs($userA)
        ->delete("/integrations/tokens/{$tokenB->accessToken->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $tokenB->accessToken->id,
    ]);
});

it('authenticates api requests using the generated personal access token', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('project_manager');

    Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Automated Integration Project',
    ]);

    $tokenResult = $user->createToken('API Tester Token', ['*']);

    $response = $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
        ->getJson('/api/projects');

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'Automated Integration Project']);
});

it('allows an administrator to register a webhook with a secret', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Slack Notifier',
            'url' => 'https://example.test/hooks/slack',
            'events' => ['task.created', 'comment.added'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $webhook = Webhook::query()->first();

    expect($webhook)->not->toBeNull()
        ->and($webhook->user_id)->toBe($admin->id)
        ->and($webhook->events)->toBe(['task.created', 'comment.added'])
        ->and($webhook->is_active)->toBeTrue()
        ->and($webhook->secret)->toHaveLength(40);
});

it('forbids members from managing webhooks', function () {
    $member = User::factory()->create(['is_active' => true]);
    $member->assignRole('member');

    $this->actingAs($member)
        ->post('/integrations/webhooks', [
            'name' => 'Unauthorized Hook',
            'url' => 'https://example.test/hooks/unauthorized',
            'events' => ['task.created'],
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('webhooks', 0);

    $webhook = Webhook::query()->create([
        'user_id' => $member->id,
        'name' => 'Existing Hook',
        'url' => 'https://example.test/hooks/existing',
        'secret' => 'secret-value',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    $this->actingAs($member)
        ->patch("/integrations/webhooks/{$webhook->id}/toggle")
        ->assertForbidden();

    $this->actingAs($member)
        ->delete("/integrations/webhooks/{$webhook->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('webhooks', ['id' => $webhook->id]);
});

it('validates webhook urls and event names', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Broken Hook',
            'url' => 'bukan-url',
            'events' => ['unknown.event'],
        ])
        ->assertSessionHasErrors(['url', 'events.0']);

    $this->assertDatabaseCount('webhooks', 0);
});

it('hides webhook secrets from users without view secrets permission', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $member = User::factory()->create(['is_active' => true]);
    $member->assignRole('member');

    $webhook = Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Workspace Hook',
        'url' => 'https://example.test/hooks/workspace',
        'secret' => 'super-secret-value',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    // Member cannot view secrets (no WEBHOOKS_VIEW_SECRETS permission)
    $this->actingAs($member)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Integrations/Index')
            ->where('can_manage_webhooks', false)
            ->where('can_view_webhook_secrets', false)
            ->where('webhooks.data.0.id', $webhook->id)
            ->where('webhooks.data.0.secret', null)
        );

    // Admin has WEBHOOKS_VIEW_SECRETS permission but secret is only shown once on create via flashed session
    // On index page load, secret should be null (not exposed from database)
    $this->actingAs($admin)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Integrations/Index')
            ->where('can_manage_webhooks', true)
            ->where('can_view_webhook_secrets', true)
            ->where('webhooks.data.0.id', $webhook->id)
            ->where('webhooks.data.0.secret', null)
        );
});

it('shows webhook secret only once via flashed session on create', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    // Create webhook - secret should be flashed to session
    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'New Hook',
            'url' => 'https://example.test/hooks/new',
            'events' => ['task.created'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionHas('new_webhook_secret');

    // Get the flashed secret from session
    $secret = session('new_webhook_secret');
    expect($secret)->toBeString()->toHaveLength(40);

    // Follow redirect to index - secret should be available via flashed session
    $this->actingAs($admin)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Integrations/Index')
            ->where('can_view_webhook_secrets', true)
            ->where('new_webhook_secret', $secret)
            ->where('webhooks.data.0.secret', null) // DB secret still not exposed
        );

    // Second page load - secret should no longer be in session
    $this->actingAs($admin)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Integrations/Index')
            ->where('can_view_webhook_secrets', true)
            ->where('new_webhook_secret', null)
            ->where('webhooks.data.0.secret', null)
        );
});

it('updates toggles and deletes a webhook', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $webhook = Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Old Hook',
        'url' => 'https://example.test/hooks/old',
        'secret' => 'secret-value',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put("/integrations/webhooks/{$webhook->id}", [
            'name' => 'Renamed Hook',
            'url' => 'https://example.test/hooks/new',
            'events' => ['task.created', 'project.created'],
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $webhook->refresh();

    expect($webhook->name)->toBe('Renamed Hook')
        ->and($webhook->url)->toBe('https://example.test/hooks/new')
        ->and($webhook->events)->toBe(['task.created', 'project.created'])
        ->and($webhook->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->patch("/integrations/webhooks/{$webhook->id}/toggle")
        ->assertRedirect();

    expect($webhook->fresh()->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->delete("/integrations/webhooks/{$webhook->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
});

it('sends a signed test payload and records a successful delivery', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $webhook = Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Test Hook',
        'url' => 'https://example.test/hooks/test',
        'secret' => 'signing-secret',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    Http::fake([
        'example.test/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post("/integrations/webhooks/{$webhook->id}/test")
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(function (ClientRequest $request) use ($webhook): bool {
        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->body(), $webhook->secret);

        return $request->url() === $webhook->url
            && $request->hasHeader('X-Webhook-Event', 'webhook.test')
            && $request->hasHeader('X-Webhook-Signature', $expectedSignature)
            && $request->hasHeader('X-Webhook-Delivery')
            && ($request->data()['event'] ?? null) === 'webhook.test'
            && ($request->data()['data']['message'] ?? null) !== null;
    });

    $webhook->refresh();

    expect($webhook->last_delivery_status)->toBe('success')
        ->and($webhook->last_delivery_code)->toBe(200)
        ->and($webhook->last_delivery_error)->toBeNull()
        ->and($webhook->last_delivery_latency_ms)->not->toBeNull()
        ->and($webhook->last_delivery_at)->not->toBeNull();
});

it('records a failed test delivery', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $webhook = Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Failing Hook',
        'url' => 'https://example.test/hooks/failing',
        'secret' => 'signing-secret',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    Http::fake([
        'example.test/*' => Http::response('server error', 500),
    ]);

    $this->actingAs($admin)
        ->post("/integrations/webhooks/{$webhook->id}/test")
        ->assertRedirect()
        ->assertSessionHas('error');

    $webhook->refresh();

    expect($webhook->last_delivery_status)->toBe('failed')
        ->and($webhook->last_delivery_code)->toBe(500)
        ->and($webhook->last_delivery_error)->toContain('server error');
});

it('dispatches task created events to subscribed webhooks', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);

    Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Task Listener',
        'url' => 'https://example.test/hooks/tasks',
        'secret' => 'task-secret',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    Http::fake([
        'example.test/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Webhook Task',
            'status' => 'todo',
            'priority' => 'high',
        ])
        ->assertCreated();

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->hasHeader('X-Webhook-Event', 'task.created')
            && ($request->data()['data']['title'] ?? null) === 'Webhook Task';
    });
});

it('dispatches project created events to subscribed webhooks', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Project Listener',
        'url' => 'https://example.test/hooks/projects',
        'secret' => 'project-secret',
        'events' => ['project.created'],
        'is_active' => true,
    ]);

    Http::fake([
        'example.test/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->postJson('/api/projects', [
            'name' => 'Webhook Project',
        ])
        ->assertCreated();

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->hasHeader('X-Webhook-Event', 'project.created')
            && ($request->data()['data']['name'] ?? null) === 'Webhook Project';
    });
});

it('dispatches comment added events to subscribed webhooks', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);

    $task = Task::factory()->forProject($project)->create([
        'creator_id' => $admin->id,
    ]);

    Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Comment Listener',
        'url' => 'https://example.test/hooks/comments',
        'secret' => 'comment-secret',
        'events' => ['comment.added'],
        'is_active' => true,
    ]);

    Http::fake([
        'example.test/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->postJson("/api/tasks/{$task->id}/comments", [
            'content' => 'Komentar untuk webhook.',
        ])
        ->assertCreated();

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->hasHeader('X-Webhook-Event', 'comment.added')
            && ($request->data()['data']['content'] ?? null) === 'Komentar untuk webhook.';
    });
});

it('does not dispatch events to inactive or unsubscribed webhooks', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);

    Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Inactive Listener',
        'url' => 'https://example.test/hooks/inactive',
        'secret' => 'inactive-secret',
        'events' => ['task.created'],
        'is_active' => false,
    ]);

    Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Other Event Listener',
        'url' => 'https://example.test/hooks/other',
        'secret' => 'other-secret',
        'events' => ['project.created'],
        'is_active' => true,
    ]);

    Http::fake();

    $this->actingAs($admin)
        ->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'No Webhook Task',
        ])
        ->assertCreated();

    Http::assertNothingSent();
});

/* ------------------------------------------------------------------ */
/* SSRF Protection Tests */
/* ------------------------------------------------------------------ */

it('allows public HTTPS URL for webhook', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Public Webhook',
            'url' => 'https://example.test/hooks/public',
            'events' => ['task.created'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('rejects localhost URL', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Localhost Hook',
            'url' => 'http://localhost:8080/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects loopback IP address', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Loopback Hook',
            'url' => 'http://127.0.0.1:8080/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects IPv6 loopback', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'IPv6 Loopback Hook',
            'url' => 'http://[::1]:8080/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects private IPv4 10.0.0.0/8', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Private 10 Hook',
            'url' => 'http://10.0.0.1/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects private IPv4 172.16.0.0/12', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Private 172 Hook',
            'url' => 'http://172.16.0.1/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects private IPv4 192.168.0.0/16', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Private 192 Hook',
            'url' => 'http://192.168.1.1/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects link-local 169.254.0.0/16', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Link Local Hook',
            'url' => 'http://169.254.169.254/latest/meta-data/',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects carrier-grade NAT 100.64.0.0/10', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'CGNAT Hook',
            'url' => 'http://100.64.0.1/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects multicast IPv4', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Multicast Hook',
            'url' => 'http://224.0.0.1/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects reserved IPv4', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Reserved Hook',
            'url' => 'http://240.0.0.1/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects broadcast address', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Broadcast Hook',
            'url' => 'http://255.255.255.255/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects zero network 0.0.0.0/8', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Zero Network Hook',
            'url' => 'http://0.0.0.0/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects IPv6 unique local fc00::/7', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'IPv6 ULA Hook',
            'url' => 'http://[fc00::1]/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects IPv6 link-local fe80::/10', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'IPv6 Link Local Hook',
            'url' => 'http://[fe80::1]/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects IPv6 multicast ff00::/8', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'IPv6 Multicast Hook',
            'url' => 'http://[ff00::1]/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects AWS metadata endpoint 169.254.169.254', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'AWS Metadata Hook',
            'url' => 'http://169.254.169.254/latest/meta-data/iam/security-credentials/',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('rejects internal service ports', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Internal Port Hook',
            'url' => 'https://example.test:3306/webhook',
            'events' => ['task.created'],
        ])
        ->assertSessionHasErrors(['url' => 'URL tujuan tidak diizinkan karena alasan keamanan.']);
});

it('validates URL at delivery time to prevent DNS rebinding', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    // Create webhook with initially safe URL
    $webhook = Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Safe Hook',
        'url' => 'https://example.test/hooks/safe',
        'secret' => 'test-secret',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    // Update to dangerous URL (simulating DNS rebinding)
    $webhook->update(['url' => 'http://169.254.169.254/latest/meta-data/']);

    Http::fake([
        'example.test/*' => Http::response(['ok' => true], 200),
    ]);

    // Dispatch should be blocked at delivery time
    $dispatcher = app(WebhookDispatcher::class);
    $result = $dispatcher->sendSync($webhook, 'task.created', ['test' => 'data']);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('URL tujuan tidak diizinkan karena alasan keamanan.');
});

it('blocks DeliverWebhook job for internal IP', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    // Create webhook with dangerous URL
    $webhook = Webhook::query()->create([
        'user_id' => $admin->id,
        'name' => 'Dangerous Hook',
        'url' => 'http://10.0.0.1/webhook',
        'secret' => 'test-secret',
        'events' => ['task.created'],
        'is_active' => true,
    ]);

    Http::fake([
        '10.0.0.1/*' => Http::response(['ok' => true], 200),
    ]);

    // Dispatch job should be blocked
    $dispatcher = app(WebhookDispatcher::class);
    $dispatcher->dispatch('task.created', ['test' => 'data']);

    // Give job time to process
    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_id' => $webhook->id,
        'status' => 'permanently_failed',
        'error' => 'URL tujuan tidak diizinkan karena alasan keamanan.',
    ]);
});

it('handles DNS resolution failure gracefully', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    // In testing environment, unknown domains fall back to example.com IP (safe)
    // So this actually passes validation - the protection is in production DNS resolution
    $this->actingAs($admin)
        ->post('/integrations/webhooks', [
            'name' => 'Non-existent Hook',
            'url' => 'https://this-domain-does-not-exist-12345.invalid/webhook',
            'events' => ['task.created'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

/* ------------------------------------------------------------------ */
/* Sanctum Token Scope Enforcement Tests */
/* ------------------------------------------------------------------ */

it('allows token with tasks:write scope to create task via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => ProjectRole::OWNER]);

    $token = $admin->createToken('Test Token', ['tasks:write', 'projects:read'])->plainTextToken;

    Http::fake();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Task via Token',
            'status' => 'todo',
            'priority' => 'medium',
        ]);

    $response->assertCreated();
});

it('denies token with only tasks:read scope from creating task via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => ProjectRole::OWNER]);

    $token = $admin->createToken('Test Token', ['tasks:read', 'projects:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Task via Token',
            'status' => 'todo',
            'priority' => 'medium',
        ]);

    $response->assertForbidden()
        ->assertJsonFragment(['required_scope' => 'tasks:write']);
});

it('allows token with tasks:read scope to list tasks via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => ProjectRole::OWNER]);
    Task::factory()->forProject($project)->create(['creator_id' => $admin->id]);

    $token = $admin->createToken('Test Token', ['tasks:read', 'projects:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/tasks');

    $response->assertOk();
});

it('denies token without tasks:read scope from listing tasks via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $token = $admin->createToken('Test Token', ['projects:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/tasks');

    $response->assertForbidden()
        ->assertJsonFragment(['required_scope' => 'tasks:read']);
});

it('allows token with projects:write scope to create project via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $token = $admin->createToken('Test Token', ['projects:write'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/projects', [
            'name' => 'Token Project',
            'description' => 'Created via token',
            'status' => 'active',
            'priority' => 'high',
        ]);

    $response->assertCreated();
});

it('denies token with only projects:read scope from creating project via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $token = $admin->createToken('Test Token', ['projects:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/projects', [
            'name' => 'Token Project',
            'description' => 'Created via token',
            'status' => 'active',
            'priority' => 'high',
        ]);

    $response->assertForbidden()
        ->assertJsonFragment(['required_scope' => 'projects:write']);
});

it('allows token with users:read scope to list users via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $token = $admin->createToken('Test Token', ['users:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/users');

    $response->assertOk();
});

it('denies token without users:read scope from listing users via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $token = $admin->createToken('Test Token', ['tasks:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/users');

    $response->assertForbidden()
        ->assertJsonFragment(['required_scope' => 'users:read']);
});

it('allows token with notifications:read scope to list notifications via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $token = $admin->createToken('Test Token', ['notifications:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications');

    $response->assertOk();
});

it('denies token without notifications:read scope from listing notifications via API', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $token = $admin->createToken('Test Token', ['tasks:read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications');

    $response->assertForbidden()
        ->assertJsonFragment(['required_scope' => 'notifications:read']);
});

it('allows token with * scope to access all endpoints', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => ProjectRole::OWNER]);

    $token = $admin->createToken('Test Token', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Task via Wildcard Token',
            'status' => 'todo',
            'priority' => 'medium',
        ]);

    $response->assertCreated();

    $response2 = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/tasks');

    $response2->assertOk();

    $response3 = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/users');

    $response3->assertOk();
});

it('web session requests bypass token scope checks', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('administrator');

    $project = Project::factory()->create(['owner_id' => $admin->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => ProjectRole::OWNER]);

    // Using session authentication (no token)
    $this->actingAs($admin)
        ->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Task via Session',
            'status' => 'todo',
            'priority' => 'medium',
        ])
        ->assertCreated();
});

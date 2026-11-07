<?php

require_once __DIR__.'/helpers.php';

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Webhook;
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

it('hides webhook secrets from users without role management permission', function () {
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

    $this->actingAs($member)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Integrations/Index')
            ->where('can_manage_webhooks', false)
            ->where('webhooks.data.0.id', $webhook->id)
            ->where('webhooks.data.0.secret', null)
        );

    $this->actingAs($admin)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_manage_webhooks', true)
            ->where('webhooks.data.0.secret', 'super-secret-value')
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

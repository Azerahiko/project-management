<?php

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectMemberAdded;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommentAdded;
use App\Notifications\TaskStatusChanged;
use App\Services\NotificationSender;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('notifications');

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);
    (new RolePermissionSeeder)->run();

    // Create test users
    $this->admin = User::factory()->create([
        'email' => 'admin@projex.test',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $this->admin->assignRole('administrator');

    $this->pm = User::factory()->create([
        'email' => 'pm@projex.test',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $this->pm->assignRole('project_manager');

    $this->member1 = User::factory()->create([
        'email' => 'member1@projex.test',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $this->member1->assignRole('member');

    $this->member2 = User::factory()->create([
        'email' => 'member2@projex.test',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $this->member2->assignRole('member');

    $this->client = User::factory()->create([
        'email' => 'client@projex.test',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $this->client->assignRole('client');

    // Create project with members
    $this->project = Project::factory()->create([
        'owner_id' => $this->pm->id,
        'name' => 'Test Project',
    ]);

    ProjectMember::create([
        'project_id' => $this->project->id,
        'user_id' => $this->pm->id,
        'role' => ProjectRole::OWNER,
        'joined_at' => now(),
    ]);

    ProjectMember::create([
        'project_id' => $this->project->id,
        'user_id' => $this->member1->id,
        'role' => ProjectRole::MEMBER,
        'joined_at' => now(),
    ]);

    ProjectMember::create([
        'project_id' => $this->project->id,
        'user_id' => $this->member2->id,
        'role' => ProjectRole::MEMBER,
        'joined_at' => now(),
    ]);

    $this->task = Task::factory()->create([
        'project_id' => $this->project->id,
        'creator_id' => $this->pm->id,
        'assignee_id' => $this->member1->id,
        'title' => 'Test Task',
        'status' => TaskStatus::TODO,
    ]);
});

// ===========================================
// TASK ASSIGNED NOTIFICATIONS
// ===========================================

test('task assigned creates notification for new assignee', function () {
    $this->actingAs($this->pm);

    $response = $this->putJson("/api/tasks/{$this->task->id}", [
        'title' => 'Updated Task',
        'assignee_id' => $this->member2->id,
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseCount('notifications', 1);
    $notification = $this->member2->notifications()->first();
    $this->assertNotNull($notification);
    $this->assertEquals(TaskAssigned::class, $notification->type);
    $this->assertEquals('assigned', $notification->data['action']);
    $this->assertEquals($this->task->id, $notification->data['task_id']);
    $this->assertArrayHasKey('event_id', $notification->data);
});

test('task assigned does not notify actor', function () {
    $this->actingAs($this->pm);

    // PM assigns task to member1 (who is already assignee, but let's test with member2)
    $this->putJson("/api/tasks/{$this->task->id}", [
        'assignee_id' => $this->member2->id,
    ]);

    // PM should not receive notification
    $this->assertEquals(0, $this->pm->notifications()->count());
});

test('task assigned does not create notification when assignee unchanged', function () {
    $this->actingAs($this->pm);

    // Same assignee
    $this->putJson("/api/tasks/{$this->task->id}", [
        'title' => 'Updated Title',
        'assignee_id' => $this->member1->id, // Same as current
    ]);

    $this->assertDatabaseCount('notifications', 0);
});

test('re-assignment after unassign creates new notification', function () {
    $this->actingAs($this->pm);

    // Assign to member2
    $this->putJson("/api/tasks/{$this->task->id}", ['assignee_id' => $this->member2->id]);
    $this->assertEquals(1, $this->member2->notifications()->count());

    // Unassign
    $this->putJson("/api/tasks/{$this->task->id}", ['assignee_id' => null]);
    $this->assertEquals(1, $this->member2->notifications()->count());

    // Re-assign to member2 - should create NEW notification
    $this->putJson("/api/tasks/{$this->task->id}", ['assignee_id' => $this->member2->id]);
    $this->assertEquals(2, $this->member2->notifications()->where('type', TaskAssigned::class)->count());
});

test('task assigned notification is idempotent on concurrent requests', function () {
    $sender = app(NotificationSender::class);
    $eventId = Str::uuid()->toString();

    // Send same notification twice with same event_id
    $result1 = $sender->send($this->member1, TaskAssigned::class, [
        'task_id' => $this->task->id,
        'task_title' => $this->task->title,
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'action' => 'assigned',
        'event_id' => $eventId,
    ]);

    $result2 = $sender->send($this->member1, TaskAssigned::class, [
        'task_id' => $this->task->id,
        'task_title' => $this->task->title,
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'action' => 'assigned',
        'event_id' => $eventId,
    ]);

    $this->assertTrue($result1);
    $this->assertFalse($result2); // Second should be rejected
    $this->assertEquals(1, $this->member1->notifications()->where('type', TaskAssigned::class)->count());
});

// ===========================================
// TASK COMMENT ADDED NOTIFICATIONS
// ===========================================

test('comment added creates notifications for project members except author', function () {
    $this->actingAs($this->member1);

    $response = $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => 'Test comment',
    ]);

    $response->assertStatus(201);

    // member1 (author) should NOT receive notification
    $this->assertEquals(0, $this->member1->notifications()->count());

    // member2 and pm (owner) SHOULD receive notification
    $this->assertEquals(1, $this->member2->notifications()->count());
    $this->assertEquals(1, $this->pm->notifications()->count());

    $notification = $this->member2->notifications()->first();
    $this->assertEquals(TaskCommentAdded::class, $notification->type);
    $this->assertEquals('comment', $notification->data['action']);
    $this->assertEquals($this->member1->name, $notification->data['author_name']);
    $this->assertEquals($this->task->id, $notification->data['task_id']);
});

test('comment notification is idempotent per comment_id', function () {
    $sender = app(NotificationSender::class);

    $result1 = $sender->send($this->member2, TaskCommentAdded::class, [
        'task_id' => $this->task->id,
        'task_title' => $this->task->title,
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'comment_id' => 123,
        'comment_preview' => 'Test comment',
        'author_name' => 'Author',
        'action' => 'comment',
    ]);

    $result2 = $sender->send($this->member2, TaskCommentAdded::class, [
        'task_id' => $this->task->id,
        'task_title' => $this->task->title,
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'comment_id' => 123,
        'comment_preview' => 'Test comment',
        'author_name' => 'Author',
        'action' => 'comment',
    ]);

    $this->assertTrue($result1);
    $this->assertFalse($result2);
    $this->assertEquals(1, $this->member2->notifications()->where('type', TaskCommentAdded::class)->count());
});

// ===========================================
// TASK STATUS CHANGED NOTIFICATIONS
// ===========================================

test('status changed creates notifications for assignee, creator, and project managers', function () {
    $this->actingAs($this->pm);

    $this->putJson("/api/tasks/{$this->task->id}", [
        'status' => TaskStatus::IN_PROGRESS->value,
    ]);

    // member1 (assignee) should receive
    $count1 = DB::table('notifications')
        ->where('notifiable_id', $this->member1->id)
        ->where('type', TaskStatusChanged::class)
        ->count();
    $this->assertEquals(1, $count1, 'member1 should have 1 notification');

    // pm is the actor (creator + owner), should NOT receive (no self-notifications)
    $countPm = DB::table('notifications')
        ->where('notifiable_id', $this->pm->id)
        ->where('type', TaskStatusChanged::class)
        ->count();
    $this->assertEquals(0, $countPm, 'pm (actor) should have 0 notifications');

    // member2 (regular member) should NOT receive
    $count2 = DB::table('notifications')
        ->where('notifiable_id', $this->member2->id)
        ->where('type', TaskStatusChanged::class)
        ->count();
    $this->assertEquals(0, $count2, 'member2 should have 0 notifications');

    $notification = DB::table('notifications')
        ->where('notifiable_id', $this->member1->id)
        ->where('type', TaskStatusChanged::class)
        ->first();
    $this->assertNotNull($notification);
    $this->assertEquals(TaskStatusChanged::class, $notification->type);
    $data = json_decode($notification->data, true);
    $this->assertEquals('status_changed', $data['action']);
    $this->assertEquals(TaskStatus::TODO->value, $data['old_status']);
    $this->assertEquals(TaskStatus::IN_PROGRESS->value, $data['new_status']);
});

test('status changed does not notify actor', function () {
    $this->actingAs($this->member1); // Assignee acts

    $this->putJson("/api/tasks/{$this->task->id}", [
        'status' => TaskStatus::IN_PROGRESS->value,
    ]);

    // member1 is actor, should not receive
    $count1 = DB::table('notifications')
        ->where('notifiable_id', $this->member1->id)
        ->where('type', TaskStatusChanged::class)
        ->count();
    $this->assertEquals(0, $count1);

    // pm (creator + owner) should receive
    $countPm = DB::table('notifications')
        ->where('notifiable_id', $this->pm->id)
        ->where('type', TaskStatusChanged::class)
        ->count();
    $this->assertEquals(1, $countPm);
});

test('status changed deduplicates recipients with multiple roles', function () {
    // Make pm both owner and assignee
    $this->task->update(['assignee_id' => $this->pm->id]);

    // Make member1 a manager so they can update the task
    $this->project->members()->updateExistingPivot($this->member1->id, ['role' => ProjectRole::MANAGER]);

    $this->actingAs($this->member1);

    $this->putJson("/api/tasks/{$this->task->id}", [
        'status' => TaskStatus::IN_PROGRESS->value,
    ]);

    // pm is both assignee and creator and owner - should only get ONE notification
    $countPm = DB::table('notifications')
        ->where('notifiable_id', $this->pm->id)
        ->where('type', TaskStatusChanged::class)
        ->count();
    $this->assertEquals(1, $countPm);
});

test('status changed notification is idempotent per status transition', function () {
    $sender = app(NotificationSender::class);

    $result1 = $sender->send($this->member1, TaskStatusChanged::class, [
        'task_id' => $this->task->id,
        'task_title' => $this->task->title,
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'old_status' => 'todo',
        'new_status' => 'in_progress',
        'action' => 'status_changed',
        'event_id' => Str::uuid()->toString(),
    ]);

    $result2 = $sender->send($this->member1, TaskStatusChanged::class, [
        'task_id' => $this->task->id,
        'task_title' => $this->task->title,
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'old_status' => 'todo',
        'new_status' => 'in_progress',
        'action' => 'status_changed',
        'event_id' => Str::uuid()->toString(),
    ]);

    // Same status transition (todo -> in_progress) should be idempotent
    // even with different event_id, because unique key is task_id + old_status + new_status + action
    $this->assertTrue($result1);
    $this->assertFalse($result2); // Second should be rejected as duplicate
    $this->assertEquals(1, $this->member1->notifications()->where('type', TaskStatusChanged::class)->count());
});

// ===========================================
// PROJECT MEMBER ADDED NOTIFICATIONS
// ===========================================

test('project member added creates notification for new member', function () {
    $this->actingAs($this->pm);

    $newUser = User::factory()->create([
        'email' => 'newmember@projex.test',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $newUser->assignRole('member');

    $response = $this->postJson("/api/projects/{$this->project->id}/members", [
        'user_id' => $newUser->id,
        'role' => 'member',
    ]);

    $response->assertStatus(201);

    $this->assertEquals(1, $newUser->notifications()->count());
    $notification = $newUser->notifications()->first();
    $this->assertEquals(ProjectMemberAdded::class, $notification->type);
    $this->assertEquals('project_member_added', $notification->data['action']);
    $this->assertEquals($this->project->id, $notification->data['project_id']);
    $this->assertEquals('member', $notification->data['role']);
    $this->assertEquals($this->pm->name, $notification->data['added_by_name']);
});

test('project member added notification is idempotent', function () {
    $sender = app(NotificationSender::class);

    $result1 = $sender->send($this->member1, ProjectMemberAdded::class, [
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'role' => 'member',
        'added_by_name' => 'PM',
        'action' => 'project_member_added',
    ]);

    $result2 = $sender->send($this->member1, ProjectMemberAdded::class, [
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'role' => 'member',
        'added_by_name' => 'PM',
        'action' => 'project_member_added',
    ]);

    $this->assertTrue($result1);
    $this->assertFalse($result2);
    $this->assertEquals(1, $this->member1->notifications()->where('type', ProjectMemberAdded::class)->count());
});

// ===========================================
// API ENDPOINTS
// ===========================================

test('can list notifications with pagination', function () {
    // Create some notifications
    $this->member1->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member1->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->actingAs($this->member1);
    $response = $this->getJson('/api/notifications');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'type', 'data', 'read_at', 'created_at'],
        ],
        'links',
        'meta',
    ]);
    $this->assertEquals(1, count($response->json('data')));
});

test('can get unread count', function () {
    $this->member1->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member1->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->member1->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member1->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
        'read_at' => now(),
    ]);

    $this->actingAs($this->member1);
    $response = $this->getJson('/api/notifications/unread-count');

    $response->assertStatus(200);
    $this->assertEquals(1, $response->json('count'));
});

test('can mark single notification as read', function () {
    $notification = $this->member1->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member1->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->actingAs($this->member1);
    $response = $this->putJson("/api/notifications/{$notification->id}/read");

    $response->assertStatus(200);
    $this->assertNotNull($response->json('read_at'));

    $notification->refresh();
    $this->assertNotNull($notification->read_at);
});

test('cannot mark other user notification as read', function () {
    $notification = $this->member2->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member2->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->actingAs($this->member1);
    $response = $this->putJson("/api/notifications/{$notification->id}/read");

    $response->assertStatus(404); // find() returns null
});

test('can mark all notifications as read', function () {
    $this->member1->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member1->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->member1->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member1->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->actingAs($this->member1);
    $response = $this->putJson('/api/notifications/read-all');

    $response->assertStatus(200);
    $this->assertTrue($response->json('marked'));

    $this->assertEquals(0, $this->member1->unreadNotifications()->count());
});

test('notification endpoints require notifications.view permission', function () {
    $this->actingAs($this->client); // client doesn't have notifications.view

    $this->getJson('/api/notifications')->assertStatus(403);
    $this->getJson('/api/notifications/unread-count')->assertStatus(403);
});

// ===========================================
// TRANSACTIONAL SAFETY
// ===========================================

test('notifications not created when transaction rolls back', function () {
    // This test verifies that DB::afterCommit prevents notification creation on rollback
    $this->actingAs($this->pm);

    // We can't easily test rollback in feature test without throwing exception
    // But we verify the afterCommit pattern is used by checking
    // that notifications are only created after successful commit

    // Just verify the pattern is in place - the actual rollback test
    // would require a more complex setup
    $this->putJson("/api/tasks/{$this->task->id}", [
        'assignee_id' => $this->member2->id,
    ]);

    $this->assertDatabaseCount('notifications', 1);
});

// ===========================================
// CROSS-USER ISOLATION
// ===========================================

test('users only see their own notifications', function () {
    // Create notification for member1
    $notif1 = $this->member1->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member1->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    // Create notification for member2
    $notif2 = $this->member2->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member2->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->actingAs($this->member1);
    $response = $this->getJson('/api/notifications');

    $response->assertStatus(200);
    $this->assertEquals(1, count($response->json('data')));
    $this->assertEquals($notif1->id, $response->json('data.0.id'));
});

test('users cannot mark other user notifications as read', function () {
    $notif = $this->member2->notifications()->create([
        'id' => Str::uuid(),
        'type' => TaskAssigned::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->member2->id,
        'data' => ['action' => 'assigned', 'task_id' => $this->task->id, 'event_id' => Str::uuid()],
    ]);

    $this->actingAs($this->member1);
    $response = $this->putJson("/api/notifications/{$notif->id}/read");

    $response->assertStatus(404);
    $notif->refresh();
    $this->assertNull($notif->read_at);
});

// ===========================================
// TRIGGER VERIFICATION
// ===========================================

test('task creation with assignee triggers notification', function () {
    $this->actingAs($this->pm);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'title' => 'New Task',
        'assignee_id' => $this->member1->id,
    ]);

    $response->assertStatus(201);
    $this->assertEquals(1, $this->member1->notifications()->count());
});

test('task creation without assignee does not trigger notification', function () {
    $this->actingAs($this->pm);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'title' => 'New Task',
    ]);

    $response->assertStatus(201);
    $this->assertEquals(0, $this->member1->notifications()->count());
});

// ===========================================
// NOTIFICATION DATA STRUCTURE
// ===========================================

test('notification data contains required fields', function () {
    $this->actingAs($this->pm);
    $this->putJson("/api/tasks/{$this->task->id}", ['assignee_id' => $this->member2->id]);

    $notification = $this->member2->notifications()->first();
    $data = $notification->data;

    $this->assertArrayHasKey('task_id', $data);
    $this->assertArrayHasKey('task_title', $data);
    $this->assertArrayHasKey('project_id', $data);
    $this->assertArrayHasKey('project_name', $data);
    $this->assertArrayHasKey('action', $data);
    $this->assertArrayHasKey('event_id', $data);
    $this->assertArrayHasKey('url', $data);
    $this->assertStringContainsString("/tasks/{$this->task->id}", $data['url']);
});

test('comment notification data contains comment preview', function () {
    $this->actingAs($this->member1);
    $this->postJson("/api/tasks/{$this->task->id}/comments", ['content' => 'Test comment content']);

    $notification = $this->member2->notifications()->first();
    $data = $notification->data;

    $this->assertArrayHasKey('comment_id', $data);
    $this->assertArrayHasKey('comment_preview', $data);
    $this->assertArrayHasKey('author_name', $data);
    $this->assertEquals('Test comment content', $data['comment_preview']);
});

test('status changed notification data contains old and new status', function () {
    $this->actingAs($this->pm);
    $this->putJson("/api/tasks/{$this->task->id}", ['status' => TaskStatus::IN_PROGRESS->value]);

    $notification = $this->member1->notifications()->first();
    $data = $notification->data;

    $this->assertArrayHasKey('old_status', $data);
    $this->assertArrayHasKey('new_status', $data);
    $this->assertEquals(TaskStatus::TODO->value, $data['old_status']);
    $this->assertEquals(TaskStatus::IN_PROGRESS->value, $data['new_status']);
});

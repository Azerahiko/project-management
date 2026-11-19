<?php

use App\Models\File;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\Concerns\InteractsWithTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class, InteractsWithTime::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

describe('Inactive user stateless API (H-6)', function () {
    it('returns 403 JSON for inactive user with API token', function () {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('developer');

        $token = $user->createToken('test-token', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users');

        $response->assertForbidden()
            ->assertJson(['message' => 'Akun Anda telah dinonaktifkan.']);
    });

    it('redirects inactive user with web session to login', function () {
        $user = User::factory()->create([
            'email' => 'inactive-web@example.com',
            'password' => bcrypt('password'),
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('developer');

        $this->actingAs($user)
            ->get('/users')
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.');
    });
});

describe('Orphan file cleanup command (M-6)', function () {
    it('deletes physical files without database records', function () {
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => 'owner']);

        $disk = Storage::disk('local');
        $disk->makeDirectory('project-files/'.$project->id);

        // Orphan file (no DB record) - make it old so cleanup picks it up
        $orphanPath = 'project-files/'.$project->id.'/orphan-'.Str::uuid().'.txt';
        $disk->put($orphanPath, 'orphan content');
        // Set mtime to 2 hours ago using disk's path method
        touch($disk->path($orphanPath), time() - 7200);

        // Referenced file (has DB record)
        $referencedPath = 'project-files/'.$project->id.'/referenced-'.Str::uuid().'.txt';
        $disk->put($referencedPath, 'referenced content');
        File::factory()->create([
            'project_id' => $project->id,
            'storage_path' => $referencedPath,
            'original_name' => 'referenced.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'uploaded_by' => $admin->id,
        ]);

        // Recent orphan (within 1 hour) should be skipped
        $recentOrphan = 'project-files/'.$project->id.'/recent-'.Str::uuid().'.txt';
        $disk->put($recentOrphan, 'recent content');
        // Leave recent orphan with current mtime (should be skipped)

        $this->artisan('files:cleanup-orphans --force')->assertExitCode(0);

        expect($disk->exists($orphanPath))->toBeFalse();
        expect($disk->exists($referencedPath))->toBeTrue();
        expect($disk->exists($recentOrphan))->toBeTrue();
    });

    it('dry-run lists orphans without deleting', function () {
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $disk = Storage::disk('local');
        $disk->makeDirectory('project-files/'.$project->id);

        $orphanPath = 'project-files/'.$project->id.'/orphan-dry-'.Str::uuid().'.txt';
        $disk->put($orphanPath, 'orphan content');
        touch($disk->path($orphanPath), time() - 7200); // 2 hours ago

        $this->artisan('files:cleanup-orphans --dry-run')
            ->expectsOutput('1 orphaned file(s) found.')
            ->expectsOutput($orphanPath)
            ->assertExitCode(0);

        expect($disk->exists($orphanPath))->toBeTrue();
    });
});

describe('File download missing returns 404 (M-8)', function () {
    it('returns 404 when storage file is missing', function () {
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => 'owner']);

        $file = File::factory()->create([
            'project_id' => $project->id,
            'storage_path' => 'project-files/'.$project->id.'/missing-'.Str::uuid().'.txt',
            'original_name' => 'missing.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'uploaded_by' => $admin->id,
        ]);

        // Ensure physical file does not exist
        Storage::disk('local')->delete($file->storage_path);

        $this->actingAs($admin)
            ->get("/api/projects/{$project->id}/files/{$file->id}/download")
            ->assertNotFound();
    });
});

describe('Cleanup idempotency keys command (H-5)', function () {
    it('removes old idempotency keys with --force', function () {
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('administrator');

        $project = Project::factory()->create(['owner_id' => $admin->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $admin->id, 'role' => 'owner']);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'creator_id' => $admin->id,
            'assignee_id' => $admin->id,
        ]);

        $oldKey = 'old-key-'.Str::random(20);
        $recentKey = 'recent-key-'.Str::random(20);

        // Create old notification with idempotency key
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TaskAssigned',
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => json_encode(['task_id' => $task->id, 'action' => 'assigned']),
            'idempotency_key' => $oldKey,
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
            'read_at' => now()->subDays(100),
        ]);

        // Create recent notification
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TaskAssigned',
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => json_encode(['task_id' => $task->id, 'action' => 'assigned']),
            'idempotency_key' => $recentKey,
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
            'read_at' => now()->subDays(30),
        ]);

        $this->artisan('notifications:cleanup-idempotency-keys --days=90 --force')
            ->assertExitCode(0);

        // Old should be gone, recent should remain
        $this->assertDatabaseMissing('notifications', ['idempotency_key' => $oldKey]);
        $this->assertDatabaseHas('notifications', ['idempotency_key' => $recentKey]);
    });
});

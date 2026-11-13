<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ProjectRole;
use App\Models\File;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

describe('Project files API authentication', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project] = createTestProjects($this->admin, $this->pm, $this->member);
    });

    it('rejects guests', function () {
        $file = File::factory()->for($this->project)->create();

        $this->getJson("/api/projects/{$this->project->id}/files")->assertUnauthorized();
        $this->deleteJson("/api/projects/{$this->project->id}/files/{$file->id}")->assertUnauthorized();
    });

    it('allows an authenticated SPA session', function () {
        File::factory()->for($this->project)->create();

        $this->actingAs($this->pm)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('allows a personal access token with the required read ability', function () {
        File::factory()->for($this->project)->create();

        $token = $this->admin->createToken('read-only', ['projects:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('allows a personal access token with the wildcard ability', function () {
        $token = $this->admin->createToken('wildcard', ['*'])->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertOk();
    });

    it('rejects a personal access token without the required ability', function () {
        $token = $this->admin->createToken('wrong-scope', ['tasks:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertForbidden()
            ->assertJsonPath('required_scope', 'projects:read');
    });

    it('rejects a personal access token without the write ability', function () {
        $file = File::factory()->for($this->project)->create();
        $token = $this->admin->createToken('read-only', ['projects:read'])->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/api/projects/{$this->project->id}/files/{$file->id}")
            ->assertForbidden()
            ->assertJsonPath('required_scope', 'projects:write');

        expect($file->fresh()->trashed())->toBeFalse();
    });
});

describe('Project files authorization', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project] = createTestProjects($this->admin, $this->pm, $this->member);
    });

    it('lets a project member read the file list', function () {
        File::factory()->count(2)->for($this->project)->create();

        $this->actingAs($this->member)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.project_id', $this->project->id);
    });

    it('denies a user who is not a project member', function () {
        File::factory()->for($this->project)->create();

        $outsider = User::factory()->create(['is_active' => true]);
        $outsider->assignRole('member');

        $this->actingAs($outsider)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertForbidden();
    });

    it('lets a client role member read files even without documents.view', function () {
        File::factory()->for($this->project)->create();

        $client = User::factory()->create(['is_active' => true]);
        $client->assignRole('client');
        ProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $client->id,
            'role' => ProjectRole::MEMBER,
        ]);

        expect($client->can('documents.view'))->toBeFalse();

        $this->actingAs($client)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('denies delete and restore to a member without projects.update', function () {
        $active = File::factory()->for($this->project)->create();
        $trashed = File::factory()->for($this->project)->create();
        $trashed->delete();

        $this->actingAs($this->member)
            ->deleteJson("/api/projects/{$this->project->id}/files/{$active->id}")
            ->assertForbidden();

        $this->actingAs($this->member)
            ->postJson("/api/projects/{$this->project->id}/files/{$trashed->id}/restore")
            ->assertForbidden();

        expect($active->fresh()->trashed())->toBeFalse()
            ->and($trashed->fresh()->trashed())->toBeTrue();
    });

    it('lets a project manager soft delete and restore a file', function () {
        $file = File::factory()->for($this->project)->create();

        $this->actingAs($this->pm)
            ->deleteJson("/api/projects/{$this->project->id}/files/{$file->id}")
            ->assertNoContent();

        expect($file->fresh()->trashed())->toBeTrue();

        $this->actingAs($this->pm)
            ->postJson("/api/projects/{$this->project->id}/files/{$file->id}/restore")
            ->assertOk();

        expect($file->fresh()->trashed())->toBeFalse();
    });
});

describe('Project files scoped binding', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project, $this->pmProject] = createTestProjects($this->admin, $this->pm, $this->member);
    });

    it('never lists files of another project', function () {
        File::factory()->for($this->project)->create();
        File::factory()->count(2)->for($this->pmProject)->create();

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('returns 404 when a file is requested through another project', function () {
        $foreign = File::factory()->for($this->pmProject)->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/projects/{$this->project->id}/files/{$foreign->id}")
            ->assertNotFound();

        expect($foreign->fresh()->trashed())->toBeFalse();
    });

    it('returns 404 when restoring a file through another project', function () {
        $foreign = File::factory()->for($this->pmProject)->create();
        $foreign->delete();

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/files/{$foreign->id}/restore")
            ->assertNotFound();

        expect($foreign->fresh()->trashed())->toBeTrue();
    });

    it('hides soft deleted files from the normal list and shows them with trashed=1', function () {
        $kept = File::factory()->for($this->project)->create();
        $removed = File::factory()->for($this->project)->create();
        $removed->delete();

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/files")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $kept->id);

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/files?trashed=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $removed->id);
    });

    it('returns 404 for a soft deleted file on the delete route', function () {
        $file = File::factory()->for($this->project)->create();
        $file->delete();

        $this->actingAs($this->admin)
            ->deleteJson("/api/projects/{$this->project->id}/files/{$file->id}")
            ->assertNotFound();
    });
});

describe('Project files soft delete', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project] = createTestProjects($this->admin, $this->pm, $this->member);
        Storage::fake('local');
    });

    it('keeps the row and the physical file after delete', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi berkas');

        $this->actingAs($this->pm)
            ->deleteJson("/api/projects/{$this->project->id}/files/{$file->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('files', ['id' => $file->id]);
        Storage::disk('local')->assertExists($file->storage_path);
    });

    it('keeps the row and the physical file after restore', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi berkas');
        $file->delete();

        $this->actingAs($this->pm)
            ->postJson("/api/projects/{$this->project->id}/files/{$file->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('files', ['id' => $file->id, 'deleted_at' => null]);
        Storage::disk('local')->assertExists($file->storage_path);
    });
});

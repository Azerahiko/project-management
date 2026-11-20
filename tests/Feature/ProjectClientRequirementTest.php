<?php

require_once __DIR__.'/helpers.php';

use App\Models\Project;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Project Client Requirement Enforcement', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->developer] = createTestUsers();

        $this->client = User::factory()->create(['is_active' => true]);
        $this->client->assignRole(Roles::CLIENT);

        $this->client2 = User::factory()->create(['is_active' => true]);
        $this->client2->assignRole(Roles::CLIENT);
    });

    it('rejects project creation without a client (422)', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/projects', [
                'name' => 'Project Without Client',
                'status' => 'active',
                'priority' => 'medium',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_ids']);
    });

    it('rejects project creation with empty client_ids array (422)', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/projects', [
                'name' => 'Project Empty Clients',
                'client_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_ids']);
    });

    it('rejects project creation when non-client user is provided as client (422)', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/projects', [
                'name' => 'Project Non Client User',
                'client_ids' => [$this->developer->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_ids'])
            ->assertJsonFragment([
                'client_ids' => ['Semua pengguna yang dipilih sebagai klien harus memiliki role client.'],
            ]);
    });

    it('succeeds when a valid client is provided and attaches client as developer (201)', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/projects', [
                'name' => 'Valid Client Project',
                'status' => 'active',
                'priority' => 'high',
                'client_ids' => [$this->client->id],
            ])
            ->assertCreated();

        $projectId = $response->json('data.id');

        $this->assertDatabaseHas('project_members', [
            'project_id' => $projectId,
            'user_id' => $this->client->id,
            'role' => 'viewer',
        ]);

        $project = Project::find($projectId);
        expect($project->clients()->pluck('users.id')->all())->toContain($this->client->id);
    });

    it('rejects updating project client_ids to empty (422)', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/projects', [
                'name' => 'Original Project',
                'client_ids' => [$this->client->id],
            ])
            ->assertCreated();

        $projectId = $response->json('data.id');

        $this->actingAs($this->admin)
            ->putJson("/api/projects/{$projectId}", [
                'client_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_ids']);
    });

    it('rejects removing the last remaining client developer (422)', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/projects', [
                'name' => 'Single Client Project',
                'client_ids' => [$this->client->id],
            ])
            ->assertCreated();

        $projectId = $response->json('data.id');

        $this->actingAs($this->admin)
            ->deleteJson("/api/projects/{$projectId}/members/{$this->client->id}")
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Proyek harus mempertahankan minimal satu klien.',
            ]);
    });

    it('allows removing a client if another client remains on the project (204)', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/projects', [
                'name' => 'Multi Client Project',
                'client_ids' => [$this->client->id, $this->client2->id],
            ])
            ->assertCreated();

        $projectId = $response->json('data.id');

        $this->actingAs($this->admin)
            ->deleteJson("/api/projects/{$projectId}/members/{$this->client->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $projectId,
            'user_id' => $this->client->id,
        ]);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $projectId,
            'user_id' => $this->client2->id,
        ]);
    });
});

<?php

require_once __DIR__.'/helpers.php';

use App\Models\Task;
use App\Models\TaskComment;

describe('Trashed Resources', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project] = createTestProjects($this->admin, $this->pm, $this->member);
        [$this->task] = createTestTasks($this->admin, $this->pm, $this->member, $this->project, $this->project, $this->project);
    });

    it('lists only trashed projects at the trashed endpoint', function () {
        $this->project->delete();

        $this->actingAs($this->admin)
            ->getJson('/api/projects/trashed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->project->id)
            ->assertJsonCount(1, 'data');
    });

    it('filters trashed projects by search', function () {
        $this->project->delete();

        $this->actingAs($this->admin)
            ->getJson('/api/projects/trashed?search=Redesain')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->admin)
            ->getJson('/api/projects/trashed?search=no-such-project')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('restores a trashed project', function () {
        $this->project->delete();

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/restore")
            ->assertOk();

        expect($this->project->fresh()->deleted_at)->toBeNull();
    });

    it('filters trashed tasks by search and status', function () {
        $this->task->delete();

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/tasks/trashed?search={$this->task->title}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/tasks/trashed?status=done")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('restores a trashed task and comment', function () {
        $comment = TaskComment::factory()->create([
            'task_id' => $this->task->id,
            'user_id' => $this->admin->id,
        ]);

        $this->task->delete();
        $comment->delete();

        $this->actingAs($this->admin)
            ->postJson("/api/tasks/{$this->task->id}/restore")
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/comments/{$comment->id}/restore")
            ->assertOk();

        expect(Task::withTrashed()->find($this->task->id)->deleted_at)->toBeNull();
        expect(TaskComment::withTrashed()->find($comment->id)->deleted_at)->toBeNull();
    });
});

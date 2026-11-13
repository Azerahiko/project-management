<?php

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

describe('File model', function () {
    it('creates the files table with the expected columns', function () {
        expect(Schema::hasTable('files'))->toBeTrue();

        foreach (['id', 'project_id', 'original_name', 'storage_path', 'mime_type', 'size', 'uploaded_by', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            expect(Schema::hasColumn('files', $column))->toBeTrue();
        }
    });

    it('persists a file with valid data', function () {
        $file = File::factory()->create([
            'original_name' => 'brief-desain.pdf',
            'storage_path' => 'project-files/1/abc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);

        expect($file->exists)->toBeTrue()
            ->and($file->original_name)->toBe('brief-desain.pdf')
            ->and($file->size)->toBe(2048);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'storage_path' => 'project-files/1/abc.pdf',
            'mime_type' => 'application/pdf',
        ]);
    });

    it('belongs to a project', function () {
        $project = Project::factory()->create();
        $file = File::factory()->for($project)->create();

        expect($file->project)->toBeInstanceOf(Project::class)
            ->and($file->project->id)->toBe($project->id);
    });

    it('belongs to the uploading user', function () {
        $uploader = User::factory()->create();
        $file = File::factory()->create(['uploaded_by' => $uploader->id]);

        expect($file->uploader)->toBeInstanceOf(User::class)
            ->and($file->uploader->id)->toBe($uploader->id);
    });

    it('is reachable from the project side', function () {
        $project = Project::factory()->create();
        File::factory()->count(2)->for($project)->create();

        expect($project->files)->toHaveCount(2)
            ->and($project->files->first())->toBeInstanceOf(File::class);
    });

    it('soft deletes without removing the row', function () {
        $file = File::factory()->create();

        $file->delete();

        expect($file->fresh()->trashed())->toBeTrue();
        $this->assertDatabaseHas('files', ['id' => $file->id]);
        $this->assertSoftDeleted('files', ['id' => $file->id]);
    });

    it('restores a soft deleted row', function () {
        $file = File::factory()->create();
        $file->delete();

        $file->restore();

        expect($file->fresh()->trashed())->toBeFalse();
        $this->assertDatabaseHas('files', ['id' => $file->id, 'deleted_at' => null]);
    });

    it('cascades rows when the project is force deleted', function () {
        $project = Project::factory()->create();
        $file = File::factory()->for($project)->create();

        $project->forceDelete();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    });

    it('keeps the row and nulls the uploader when the user is deleted', function () {
        $uploader = User::factory()->create();
        $file = File::factory()->create(['uploaded_by' => $uploader->id]);

        $uploader->delete();

        $this->assertDatabaseHas('files', ['id' => $file->id, 'uploaded_by' => null]);
    });

    it('rejects a duplicate storage path', function () {
        $file = File::factory()->create(['storage_path' => 'project-files/1/duplicate.pdf']);

        expect(fn () => File::factory()->create(['storage_path' => $file->storage_path]))
            ->toThrow(QueryException::class);
    });
});

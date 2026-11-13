<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ProjectRole;
use App\Models\File;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Create an uploaded file whose content is what the server actually inspects.
 */
function realUpload(string $name, string $content, ?string $mimeType = null): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, $mimeType, null, true);
}

describe('Project file upload', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project, , $this->otherProject] = createTestProjects($this->admin, $this->pm, $this->member);
        Storage::fake('local');
    });

    it('stores a pdf on the private disk with server generated metadata', function () {
        $response = $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create('Laporan Akhir.pdf', 120),
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'Laporan Akhir.pdf')
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonPath('data.uploaded_by', $this->pm->id)
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonMissingPath('data.storage_path');

        $file = File::findOrFail($response->json('data.id'));

        expect($file->storage_path)
            ->toMatch('/^project-files\/'.$this->project->id.'\/[0-9a-f-]{36}\.pdf$/')
            ->and($file->original_name)->toBe('Laporan Akhir.pdf')
            ->and($file->size)->toBe(120 * 1024);

        Storage::disk('local')->assertExists($file->storage_path);
    });

    it('accepts the supported image, office, text and zip formats', function (string $name) {
        $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create($name, 10),
            ])
            ->assertCreated();

        expect(File::count())->toBe(1);
    })->with([
        'photo.png',
        'photo.webp',
        'laporan.docx',
        'laporan.xlsx',
        'laporan.pptx',
        'catatan.txt',
        'data.csv',
        'arsip.zip',
    ]);

    it('keeps the client name but never the client path, project or uploader', function () {
        $response = $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create('rencana.pdf', 10),
                'storage_path' => 'project-files/999/evil.pdf',
                'project_id' => $this->otherProject->id,
                'uploaded_by' => $this->admin->id,
                'mime_type' => 'application/x-msdownload',
                'size' => 1,
            ])
            ->assertCreated();

        $file = File::findOrFail($response->json('data.id'));

        expect($file->project_id)->toBe($this->project->id)
            ->and($file->uploaded_by)->toBe($this->pm->id)
            ->and($file->storage_path)->not->toBe('project-files/999/evil.pdf')
            ->and($file->mime_type)->toBe('application/pdf')
            ->and($file->size)->toBe(10 * 1024);
    });

    it('rejects a file larger than 25 MB', function () {
        $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create('besar.pdf', 26 * 1024),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        expect(File::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('rejects a disallowed mime type', function () {
        $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create('video.mp4', 10),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('rejects a fake extension', function () {
        $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => realUpload('dokumen.pdf', "<?php echo 'pwned';"),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        expect(File::count())->toBe(0);
    });

    it('rejects a file without detectable content', function () {
        $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => realUpload('kosong.pdf', ''),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    });

    it('rejects a request without a file', function () {
        $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    });

    it('rejects guests', function () {
        $this->post("/api/projects/{$this->project->id}/files", [
            'file' => UploadedFile::fake()->create('rencana.pdf', 10),
        ])->assertUnauthorized();
    });

    it('rejects a project member without projects.update', function () {
        $this->actingAs($this->member)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create('rencana.pdf', 10),
            ])
            ->assertForbidden();

        expect(File::count())->toBe(0);
    });

    it('rejects a client role member', function () {
        $client = User::factory()->create(['is_active' => true]);
        $client->assignRole('client');
        ProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $client->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($client)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create('rencana.pdf', 10),
            ])
            ->assertForbidden();
    });

    it('rejects a user of another project', function () {
        $this->actingAs($this->member)
            ->post("/api/projects/{$this->otherProject->id}/files", [
                'file' => UploadedFile::fake()->create('rencana.pdf', 10),
            ])
            ->assertForbidden();

        expect(File::count())->toBe(0);
    });

    it('stores a file with an original name longer than 255 characters', function () {
        $longName = str_repeat('a', 300).'.pdf';

        $response = $this->actingAs($this->pm)
            ->post("/api/projects/{$this->project->id}/files", [
                'file' => UploadedFile::fake()->create($longName, 10),
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', $longName);

        $file = File::findOrFail($response->json('data.id'));
        expect($file->original_name)->toBe($longName);
    });

    it('removes the stored file when the database insert fails', function () {
        File::saving(function () {
            throw new \RuntimeException('Database failure simulation');
        });

        try {
            $this->actingAs($this->pm)
                ->post("/api/projects/{$this->project->id}/files", [
                    'file' => UploadedFile::fake()->create('test.pdf', 10),
                ])
                ->assertStatus(500)
                ->assertJsonPath('message', 'Berkas gagal disimpan.');

            expect(Storage::disk('local')->allFiles())->toBe([]);
        } finally {
            File::flushEventListeners();
        }
    });
});

describe('Project file download', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project, , $this->otherProject] = createTestProjects($this->admin, $this->pm, $this->member);
        Storage::fake('local');
    });

    it('downloads through the authorized route using the original name', function () {
        $file = File::factory()->for($this->project)->create(['original_name' => 'Laporan Akhir.pdf']);
        Storage::disk('local')->put($file->storage_path, 'isi berkas');

        $response = $this->actingAs($this->member)
            ->get("/api/projects/{$this->project->id}/files/{$file->id}/download")
            ->assertOk()
            ->assertDownload('Laporan Akhir.pdf');

        expect($response->streamedContent())->toBe('isi berkas');
    });

    it('rejects guests', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi');

        $this->get("/api/projects/{$this->project->id}/files/{$file->id}/download")
            ->assertUnauthorized();
    });

    it('rejects a user without access to the project', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi');

        $outsider = User::factory()->create(['is_active' => true]);
        $outsider->assignRole('member');

        $this->actingAs($outsider)
            ->get("/api/projects/{$this->project->id}/files/{$file->id}/download")
            ->assertForbidden();
    });

    it('returns 404 for a file of another project', function () {
        $foreign = File::factory()->for($this->otherProject)->create();
        Storage::disk('local')->put($foreign->storage_path, 'isi');

        $this->actingAs($this->admin)
            ->get("/api/projects/{$this->project->id}/files/{$foreign->id}/download")
            ->assertNotFound();
    });

    it('returns 404 for a soft deleted file', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi');
        $file->delete();

        $this->actingAs($this->pm)
            ->get("/api/projects/{$this->project->id}/files/{$file->id}/download")
            ->assertNotFound();
    });

    it('returns 404 when the physical file is missing', function () {
        $file = File::factory()->for($this->project)->create();

        $this->actingAs($this->pm)
            ->get("/api/projects/{$this->project->id}/files/{$file->id}/download")
            ->assertNotFound();
    });

    it('sends a safe disposition header for names with special characters', function () {
        $file = File::factory()->for($this->project)->create([
            'original_name' => 'Laporan "Akhir" – Q3.pdf',
        ]);
        Storage::disk('local')->put($file->storage_path, 'isi');

        $response = $this->actingAs($this->pm)
            ->get("/api/projects/{$this->project->id}/files/{$file->id}/download")
            ->assertOk();

        expect($response->headers->get('content-disposition'))
            ->toContain('attachment')
            ->toContain("filename*=utf-8''");
    });

    it('lets a client role member download', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi');

        $client = User::factory()->create(['is_active' => true]);
        $client->assignRole('client');
        ProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $client->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($client)
            ->get("/api/projects/{$this->project->id}/files/{$file->id}/download")
            ->assertOk();
    });
});

describe('Project file storage cleanup', function () {
    beforeEach(function () {
        [$this->admin, $this->pm, $this->member] = createTestUsers();
        [$this->project] = createTestProjects($this->admin, $this->pm, $this->member);
        Storage::fake('local');
    });

    it('keeps the physical file on soft delete and restore', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi');

        $file->delete();
        Storage::disk('local')->assertExists($file->storage_path);

        $file->restore();
        Storage::disk('local')->assertExists($file->storage_path);
        expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    });

    it('deletes the physical file on force delete', function () {
        $file = File::factory()->for($this->project)->create();
        Storage::disk('local')->put($file->storage_path, 'isi');

        $file->forceDelete();

        Storage::disk('local')->assertMissing($file->storage_path);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    });

    it('survives a force delete when the physical file is already gone', function () {
        $file = File::factory()->for($this->project)->create();

        $file->forceDelete();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    });

    it('never deletes files outside the project-files folder', function () {
        Storage::disk('local')->put('other/keep.txt', 'jangan hapus');
        $file = File::factory()->for($this->project)->create(['storage_path' => 'other/keep.txt']);

        $file->forceDelete();

        Storage::disk('local')->assertExists('other/keep.txt');
    });

    it('cleans physical files when a project is force deleted', function () {
        $active = File::factory()->for($this->project)->create();
        $trashed = File::factory()->for($this->project)->create();
        $trashed->delete();

        Storage::disk('local')->put($active->storage_path, 'isi');
        Storage::disk('local')->put($trashed->storage_path, 'isi');

        $this->project->forceDelete();

        Storage::disk('local')->assertMissing($active->storage_path);
        Storage::disk('local')->assertMissing($trashed->storage_path);
        expect(Storage::disk('local')->allFiles())->toBe([]);
        $this->assertDatabaseMissing('files', ['project_id' => $this->project->id]);
    });
});

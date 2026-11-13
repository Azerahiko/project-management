<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectFileRequest;
use App\Http\Resources\ProjectFileResource;
use App\Models\File;
use App\Models\Project;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectFileController extends Controller
{
    /**
     * List the files of a project. Pass ?trashed=1 to list soft-deleted files.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $files = $project->files()
            ->with('uploader')
            ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
            ->latest()
            ->paginate(Pagination::perPage($request, 15, 100), ['*'], 'page', Pagination::page($request))
            ->withQueryString();

        return ProjectFileResource::collection($files);
    }

    /**
     * Store an uploaded file on the private disk and record its metadata.
     */
    public function store(StoreProjectFileRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $upload = $request->file('file');

        // Physical name is generated server-side; the client name is metadata only.
        $storagePath = $upload->storeAs(
            "project-files/{$project->id}",
            Str::uuid()->toString().'.'.$upload->guessExtension(),
            'local'
        );

        try {
            $file = $project->files()->create([
                'original_name' => $upload->getClientOriginalName(),
                'storage_path' => $storagePath,
                'mime_type' => $upload->getMimeType(),
                'size' => $upload->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            // Storage and database are not one transaction: never leave an orphan file.
            Storage::disk('local')->delete($storagePath);
            report($exception);

            return response()->json(['message' => 'Berkas gagal disimpan.'], 500);
        }

        return ProjectFileResource::make($file->load('uploader'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Download the file through an authorized route, never through a public URL.
     */
    public function download(Project $project, File $file): StreamedResponse
    {
        $this->authorize('view', $project);

        try {
            return Storage::disk('local')->download($file->storage_path, $file->original_name, [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\League\Flysystem\UnableToRetrieveMetadata $e) {
            abort(404);
        }
    }

    /**
     * Soft delete a file. The physical file is kept until a force delete.
     */
    public function destroy(Project $project, File $file): JsonResponse
    {
        $this->authorize('update', $project);

        $file->delete();

        return response()->json(status: 204);
    }

    /**
     * Restore a soft-deleted file of the project.
     */
    public function restore(Project $project, File $file): JsonResponse
    {
        $this->authorize('update', $project);

        $file->restore();

        return response()->json(['message' => 'Berkas berhasil dipulihkan.']);
    }
}

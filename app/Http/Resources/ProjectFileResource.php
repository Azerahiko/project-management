<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var File $file */
        $file = $this->resource;

        return [
            'id' => $file->id,
            'project_id' => $file->project_id,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'uploaded_by' => $file->uploaded_by,
            'created_at' => $file->created_at?->toISOString(),
            'updated_at' => $file->updated_at?->toISOString(),
            'deleted_at' => $file->deleted_at?->toISOString(),
            'uploader' => $this->uploaderData($file),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploaderData(File $file): array|null
    {
        if (! $file->relationLoaded('uploader') || ! $file->uploader) {
            return null;
        }

        $user = $file->uploader;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
        ];
    }
}

<?php

namespace App\Models;

use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $project_id
 * @property string $original_name
 * @property string $storage_path
 * @property string $mime_type
 * @property int $size
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read User|null $uploader
 */
#[Fillable(['project_id', 'original_name', 'storage_path', 'mime_type', 'size', 'uploaded_by'])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'size' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The physical file is only removed on force delete; soft delete keeps it for restore.
        static::forceDeleted(function (File $file): void {
            if (! str_starts_with($file->storage_path, 'project-files/')) {
                return;
            }

            Storage::disk('local')->delete($file->storage_path);
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

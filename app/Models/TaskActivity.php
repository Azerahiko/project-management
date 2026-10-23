<?php

namespace App\Models;

use Database\Factories\TaskActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $activity_type
 * @property string|null $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string $description
 * @property Carbon|null $created_at
 * @property-read Task $task
 * @property-read User|null $user
 */
#[Fillable(['task_id', 'user_id', 'activity_type', 'field', 'old_value', 'new_value', 'description'])]
class TaskActivity extends Model
{
    /** @use HasFactory<TaskActivityFactory> */
    use HasFactory;

    protected $table = 'task_activities';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

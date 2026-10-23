<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskCommentRequest;
use App\Http\Requests\Api\UpdateTaskCommentRequest;
use App\Http\Resources\TaskCommentResource;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\TaskActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskCommentController extends Controller
{
    public function __construct(
        protected TaskActivityLogger $activityLogger
    ) {}

    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $perPage = min((int) $request->integer('per_page', 15), 50);
        $page = (int) $request->integer('page', 1);

        $comments = $task->comments()
            ->with('user')
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        return TaskCommentResource::collection($comments);
    }

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('create', [TaskComment::class, $task]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        $this->activityLogger->log(
            $task,
            'comment.created',
            null,
            null,
            $comment->content,
            $request->user()
        );

        $comment->load('user');

        return TaskCommentResource::make($comment)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTaskCommentRequest $request, TaskComment $comment): TaskCommentResource
    {
        $this->authorize('update', $comment);

        $oldContent = $comment->content;

        $comment->update([
            'content' => $request->validated('content'),
        ]);

        $this->activityLogger->log(
            $comment->task,
            'comment.updated',
            'content',
            $oldContent,
            $comment->content,
            $request->user()
        );

        return TaskCommentResource::make($comment->load('user'));
    }

    public function destroy(Request $request, TaskComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $task = $comment->task;
        $content = $comment->content;

        $comment->delete();

        $this->activityLogger->log(
            $task,
            'comment.deleted',
            'content',
            $content,
            null,
            $request->user()
        );

        return response()->json(status: 204);
    }
}

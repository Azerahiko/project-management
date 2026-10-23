<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskActivityResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskActivityController extends Controller
{
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $limit = min((int) $request->integer('limit', 50), 100);

        $activities = $task->activities()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();

        return TaskActivityResource::collection($activities);
    }
}

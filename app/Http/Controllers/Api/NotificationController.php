<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Get paginated list of notifications for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = Pagination::perPage($request, 15, 50);
        $page = Pagination::page($request);
        $filter = $request->string('filter')->toString();

        $query = $request->user()->notifications()->latest();

        match ($filter) {
            'unread' => $query->whereNull('read_at'),
            'tasks' => $query->whereIn('data->action', [
                'assigned',
                'comment',
                'status_changed',
            ]),
            'projects' => $query->where('data->action', 'project_member_added'),
            default => null,
        };

        $notifications = $query->paginate($perPage, ['*'], 'page', $page);

        return NotificationResource::collection($notifications);
    }

    /**
     * Get unread notifications count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $request->user()->notifications()->find($id);

        if (! $notification) {
            return response()->json(['message' => 'Notifikasi tidak ditemukan'], 404);
        }

        $notification->markAsRead();

        return response()->json(['read_at' => $notification->read_at?->toISOString()]);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json(['marked' => true]);
    }
}

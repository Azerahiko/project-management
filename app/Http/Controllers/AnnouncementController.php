<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * Display the team announcements page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(Permissions::DASHBOARD_VIEW);

        $search = trim((string) $request->query('search', ''));
        $filter = (string) $request->query('filter', 'all');
        $filter = in_array($filter, ['all', 'pinned', 'recent', 'month'], true)
            ? $filter
            : 'all';

        $announcements = Announcement::query()
            ->with('user:id,name,avatar')
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($like): void {
                    $query->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(body) LIKE ?', [$like])
                        ->orWhereHas('user', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            })
            ->when($filter === 'pinned', fn ($query) => $query->where('is_pinned', true))
            ->when($filter === 'recent', fn ($query) => $query->whereRaw('COALESCE(published_at, created_at) >= ?', [now()->subDays(7)]))
            ->when($filter === 'month', fn ($query) => $query->whereRaw('COALESCE(published_at, created_at) >= ?', [now()->startOfMonth()]))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9)
            ->withQueryString();

        $summary = [
            'total' => Announcement::count(),
            'pinned' => Announcement::where('is_pinned', true)->count(),
            'recent' => Announcement::whereRaw('COALESCE(published_at, created_at) >= ?', [now()->subDays(7)])->count(),
            'this_month' => Announcement::whereRaw('COALESCE(published_at, created_at) >= ?', [now()->startOfMonth()])->count(),
            'contributors' => Announcement::distinct()->count('user_id'),
        ];

        return Inertia::render('Announcements/Index', [
            'filters' => [
                'search' => $search,
                'filter' => $filter,
            ],
            'summary' => $summary,
            'announcements' => $announcements->through(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'is_pinned' => $announcement->is_pinned,
                'published_at' => ($announcement->published_at ?? $announcement->created_at)?->toISOString(),
                'author' => [
                    'id' => $announcement->user->id,
                    'name' => $announcement->user->name,
                    'avatar' => $announcement->user->avatar,
                    'initials' => $announcement->user->initials,
                ],
            ]),
            'can_manage' => $request->user()->can(Permissions::TEAMS_VIEW),
        ]);
    }

    /**
     * Store a newly created announcement.
     */
    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Announcement::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $validated['is_pinned'] ?? false,
            'published_at' => now(),
        ]);

        return back()->with('success', 'Pengumuman berhasil dibuat.');
    }

    /**
     * Toggle the pinned state of an announcement.
     */
    public function togglePin(Announcement $announcement): RedirectResponse
    {
        Gate::authorize(Permissions::TEAMS_VIEW);

        $announcement->update(['is_pinned' => ! $announcement->is_pinned]);

        return back()->with(
            'success',
            $announcement->is_pinned
                ? 'Pengumuman berhasil disematkan.'
                : 'Pengumuman berhasil dilepas dari sematan.'
        );
    }

    /**
     * Remove the specified announcement.
     */
    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize(Permissions::TEAMS_VIEW);

        $announcement->delete();

        return back()->with('success', 'Pengumuman berhasil dihapus.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\NotificationPayload;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type' => ['nullable', 'string', 'max:80'],
            'severity' => ['nullable', 'in:info,warning,critical'],
            'is_read' => ['nullable', 'in:0,1'],
            'q' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->user()->notifications()->latest();

        if ($request->filled('type')) {
            $query->whereRaw("(CAST(data AS jsonb)->>'type') = ?", [(string) $request->type]);
        }

        if ($request->filled('severity')) {
            $query->whereRaw("(CAST(data AS jsonb)->>'severity') = ?", [(string) $request->severity]);
        }

        if ($request->filled('is_read')) {
            if ($request->is_read === '1') {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        } else {
            // Default index behavior: unread notifications only.
            $query->whereNull('read_at');
        }

        if ($request->filled('q')) {
            $q = strtolower((string) $request->q);
            $query->whereRaw('LOWER(CAST(data AS TEXT)) LIKE ?', ["%{$q}%"]);
        }

        $perPage = (int) ($request->limit ?? 15);
        $items = $query->paginate($perPage);
        $items->setCollection(
            $items->getCollection()->map(
                static fn (DatabaseNotification $notification): array => NotificationPayload::present($notification)
            )
        );

        return response()->json($items);
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json(['count' => $count]);
    }

    public function filtersMeta(Request $request)
    {
        $allData = $request->user()->notifications()->get()->map(function (DatabaseNotification $notification): array {
            return is_array($notification->data) ? $notification->data : [];
        });

        $types = $allData
            ->pluck('type')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $severities = $allData
            ->pluck('severity')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'types' => $types,
            'severities' => $severities,
        ]);
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'notification_ids' => ['required', 'array', 'min:1'],
            'notification_ids.*' => ['required', 'string'],
        ]);

        $ids = array_values(array_unique($request->input('notification_ids', [])));
        $notifications = $request->user()
            ->unreadNotifications()
            ->whereIn('id', $ids)
            ->get();

        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notifications marked as read.',
            'updated' => $notifications->count(),
            'notifications' => $notifications
                ->map(static fn (DatabaseNotification $notification): array => NotificationPayload::present($notification))
                ->values(),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'updated' => $updated,
        ]);
    }
}

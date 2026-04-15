<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

final class NotificationPayload
{
    public static function make(
        string $type,
        string $title,
        string $body,
        string $severity = 'info',
        ?string $actionUrl = null,
        array $entity = [],
        array $meta = []
    ): array {
        return [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'severity' => $severity,
            'action_url' => $actionUrl,
            'entity' => $entity,
            'meta' => $meta,
        ];
    }

    /**
     * Normalize any native Laravel notification row to one frontend-safe shape.
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => (string) $notification->id,
            'type' => (string) ($data['type'] ?? class_basename((string) $notification->type)),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'severity' => (string) ($data['severity'] ?? 'info'),
            'action_url' => isset($data['action_url']) ? (string) $data['action_url'] : null,
            'entity' => is_array($data['entity'] ?? null) ? $data['entity'] : [],
            'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }
}

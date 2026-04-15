<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class ChatPresence
{
    private const ONLINE_TTL_SECONDS = 90;
    private const ACTIVE_CONVERSATION_TTL_SECONDS = 45;

    public static function touchOnline(int $userId): void
    {
        Cache::put(self::onlineKey($userId), true, now()->addSeconds(self::ONLINE_TTL_SECONDS));
    }

    public static function isOnline(int $userId): bool
    {
        return (bool) Cache::get(self::onlineKey($userId), false);
    }

    public static function markActiveConversation(int $userId, int $conversationId): void
    {
        Cache::put(
            self::activeConversationKey($userId),
            $conversationId,
            now()->addSeconds(self::ACTIVE_CONVERSATION_TTL_SECONDS)
        );
    }

    public static function clearActiveConversation(int $userId, ?int $conversationId = null): void
    {
        if ($conversationId === null) {
            Cache::forget(self::activeConversationKey($userId));
            return;
        }

        if (self::activeConversationId($userId) === $conversationId) {
            Cache::forget(self::activeConversationKey($userId));
        }
    }

    public static function activeConversationId(int $userId): ?int
    {
        $value = Cache::get(self::activeConversationKey($userId));

        return is_numeric($value) ? (int) $value : null;
    }

    public static function shouldCreateMessageNotification(int $recipientUserId, int $conversationId): bool
    {
        if (!self::isOnline($recipientUserId)) {
            return true;
        }

        return self::activeConversationId($recipientUserId) !== $conversationId;
    }

    private static function onlineKey(int $userId): string
    {
        return 'chat:presence:online:'.$userId;
    }

    private static function activeConversationKey(int $userId): string
    {
        return 'chat:presence:conversation:'.$userId;
    }
}

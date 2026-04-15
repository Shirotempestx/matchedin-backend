<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private conversation channel.
 * Only the two participants can subscribe.
 */
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation && $conversation->hasParticipant($user);
});

/**
 * Native Laravel notification private user channel.
 */
Broadcast::channel('App.Models.User.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

/**
 * Private user channel for presence / new conversation notifications.
 */
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

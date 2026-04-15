<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Support\ChatPresence;
use Illuminate\Http\Request;

class ChatPresenceController extends Controller
{
    public function updatePresence(Request $request)
    {
        $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $rawConversationId = $request->input('conversation_id');
        $conversationId = is_numeric($rawConversationId) ? (int) $rawConversationId : null;
        $isActive = (bool) $request->input('is_active', true);

        ChatPresence::touchOnline($user->id);

        if ($conversationId !== null) {
            $conversation = Conversation::find($conversationId);
            if (!$conversation || !$conversation->hasParticipant($user)) {
                return response()->json(['message' => 'Unauthorized conversation presence update.'], 403);
            }

            if ($isActive) {
                ChatPresence::markActiveConversation($user->id, $conversationId);
            } else {
                ChatPresence::clearActiveConversation($user->id, $conversationId);
            }
        }

        return response()->json([
            'online' => true,
            'active_conversation_id' => ChatPresence::activeConversationId($user->id),
        ]);
    }
}

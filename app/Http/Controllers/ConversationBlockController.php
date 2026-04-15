<?php

namespace App\Http\Controllers;

use App\Events\UserBlocked;
use App\Models\Conversation;
use App\Models\ConversationBlock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationBlockController extends Controller
{
    /**
     * Block a conversation.
     * Creates a block record and broadcasts the event.
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$conversation->hasParticipant($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if already blocked by this user
        if ($conversation->isBlockedBy($user)) {
            return response()->json(['message' => 'Already blocked.'], 422);
        }

        ConversationBlock::create([
            'conversation_id' => $conversation->id,
            'blocker_id'      => $user->id,
        ]);

        // ── Broadcast block event ──
        broadcast(new UserBlocked($conversation->id, $user->id, true))->toOthers();

        return response()->json(['message' => 'Conversation blocked.']);
    }

    /**
     * Unblock a conversation.
     * Removes the block record and broadcasts the event.
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$conversation->hasParticipant($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $block = ConversationBlock::where('conversation_id', $conversation->id)
            ->where('blocker_id', $user->id)
            ->first();

        if (!$block) {
            return response()->json(['message' => 'Not blocked.'], 422);
        }

        $block->delete();

        // ── Broadcast unblock event ──
        broadcast(new UserBlocked($conversation->id, $user->id, false))->toOthers();

        return response()->json(['message' => 'Conversation unblocked.']);
    }
}

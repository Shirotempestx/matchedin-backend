<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\MessagesRead;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use App\Support\ChatPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Fetch messages for a conversation with cursor-based pagination.
     * Returns 50 messages per page, ordered newest-first.
     * Supports infinite scroll by loading older messages as user scrolls up.
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$conversation->hasParticipant($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        ChatPresence::touchOnline($user->id);
        ChatPresence::markActiveConversation($user->id, $conversation->id);

        $messages = $conversation->messages()
            ->with('sender:id,name,avatar_url,logo_url,role')
            ->orderByDesc('created_at')
            ->cursorPaginate(50);

        return response()->json($messages);
    }

    /**
     * Send a new message in a conversation.
     * - Validates the conversation isn't blocked
     * - Saves the message
     * - Updates conversation's last_message_at
     * - Broadcasts MessageSent event via WebSocket
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$conversation->hasParticipant($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // ── Block check ──
        if ($conversation->isBlocked()) {
            return response()->json([
                'message' => __('messages.conversation_blocked'),
            ], 403);
        }

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        // ── Create the message ──
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'body'            => $request->body,
        ]);

        // ── Update conversation timestamp ──
        $conversation->update(['last_message_at' => now()]);

        // ── Load sender relationship for the broadcast payload ──
        $message->load('sender:id,name,avatar_url,logo_url,role');

        // ── Broadcast in real-time via WebSocket ──
        broadcast(new MessageSent($message))->toOthers();

        // Only create global notification when recipient is offline or not focused on this conversation.
        $recipient = $conversation->otherParticipant($user);
        if ($recipient && ChatPresence::shouldCreateMessageNotification($recipient->id, $conversation->id)) {
            $recipient->notify(new NewChatMessageNotification($conversation, $message, $user));
        }

        return response()->json([
            'message' => $message,
        ], 201);
    }

    /**
     * Edit a message.
     * STRICTLY restricted to the sender's LAST message in the conversation.
     * Older messages cannot be edited.
     */
    public function update(Request $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Must be the sender
        if ($message->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Must be the last message sent by this user
        if (!$message->isLastBySender()) {
            return response()->json([
                'message' => __('messages.can_only_edit_last_message'),
            ], 422);
        }

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $message->update([
            'body'      => $request->body,
            'is_edited' => true,
        ]);

        // ── Broadcast the update ──
        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['message' => $message]);
    }

    /**
     * Soft-delete (remove) a message.
     * Only the sender can remove their own messages.
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($message->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $conversationId = $message->conversation_id;
        $messageId = $message->id;

        $message->delete(); // Soft delete

        // ── Broadcast the deletion ──
        broadcast(new MessageDeleted($messageId, $conversationId))->toOthers();

        return response()->json(['message' => 'Message removed.']);
    }

    /**
     * Report a message for admin review.
     * Cannot report your own messages.
     */
    public function report(Request $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Cannot report your own message
        if ($message->sender_id === $user->id) {
            return response()->json(['message' => 'Cannot report your own message.'], 422);
        }

        // Check if already reported by this user
        $exists = MessageReport::where('message_id', $message->id)
            ->where('reporter_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already reported.'], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        MessageReport::create([
            'message_id'  => $message->id,
            'reporter_id' => $user->id,
            'reason'      => $request->reason,
        ]);

        return response()->json(['message' => 'Message reported successfully.']);
    }

    /**
     * Translate a message using the existing premium translation service.
     */
    public function translate(Request $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Ensure user is a participant of the conversation
        if (!$message->conversation->hasParticipant($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $targetLocale = $request->input('locale', $user->preferred_language ?? 'fr');

        // Reuse the existing PremiumServiceController's translation logic
        try {
            $premiumService = app(\App\Http\Controllers\PremiumServiceController::class);

            // Create a synthetic request for the translate method
            $translateRequest = Request::create('/premium/translate', 'POST', [
                'text'        => $message->body,
                'target_lang' => $targetLocale,
            ]);
            $translateRequest->setUserResolver(fn() => $user);

            $response = $premiumService->translate($translateRequest);
            $data = json_decode($response->getContent(), true);

            return response()->json([
                'original'   => $message->body,
                'translated' => $data['translated'] ?? $message->body,
                'locale'     => $targetLocale,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message'    => 'Translation failed.',
                'translated' => $message->body,
            ], 500);
        }
    }

    /**
     * Mark all unread messages in a conversation as read.
     * Broadcasts MessagesRead event for read receipts.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$conversation->hasParticipant($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        ChatPresence::touchOnline($user->id);
        ChatPresence::markActiveConversation($user->id, $conversation->id);

        // Mark all messages from the OTHER user as read
        $updated = Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($updated > 0) {
            // ── Broadcast read receipt ──
            broadcast(new MessagesRead($conversation->id, $user->id))->toOthers();
        }

        return response()->json(['read_count' => $updated]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    /**
     * List all conversations for the authenticated user.
     * Returns conversations ordered by last_message_at with unread counts.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversations = Conversation::where('participant_one_id', $user->id)
            ->orWhere('participant_two_id', $user->id)
            ->with(['participantOne:id,name,avatar_url,logo_url,role', 'participantTwo:id,name,avatar_url,logo_url,role', 'latestMessage'])
            ->withCount(['blocks'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        // Transform to include computed fields
        $conversations->getCollection()->transform(function (Conversation $conversation) use ($user) {
            $other = $conversation->otherParticipant($user);

            return [
                'id'               => $conversation->id,
                'other_participant' => $other ? [
                    'id'         => $other->id,
                    'name'       => $other->name,
                    'avatar_url' => $other->avatar_url ?? $other->logo_url ?? null,
                    'role'       => $other->role,
                ] : null,
                'latest_message'   => $conversation->latestMessage ? [
                    'id'         => $conversation->latestMessage->id,
                    'body'       => $conversation->latestMessage->body,
                    'sender_id'  => $conversation->latestMessage->sender_id,
                    'created_at' => $conversation->latestMessage->created_at,
                    'is_read'    => $conversation->latestMessage->is_read,
                ] : null,
                'unread_count'     => $conversation->unreadCountFor($user),
                'is_blocked'       => $conversation->blocks_count > 0,
                'blocked_by_me'    => $conversation->isBlockedBy($user),
                'last_message_at'  => $conversation->last_message_at,
                'created_at'       => $conversation->created_at,
            ];
        });

        return response()->json($conversations);
    }

    /**
     * Create a new conversation or return existing one.
     * Enforces ConversationPolicy authorization.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'target_user_id' => 'required|exists:users,id',
        ]);

        /** @var User $user */
        $user = $request->user();
        $target = User::findOrFail($request->target_user_id);

        // Check if conversation already exists
        $existing = Conversation::between($user->id, $target->id);

        if ($existing) {
            return response()->json([
                'conversation' => $this->formatConversation($existing, $user),
                'is_new'       => false,
            ]);
        }

        // ── Authorization check ──
        // Uses ConversationPolicy@create to evaluate tier-based rules
        if (!Gate::allows('create', [Conversation::class, $target])) {
            return response()->json([
                'message' => __('messages.cannot_initiate_conversation'),
            ], 403);
        }

        // Normalize participant order (lower ID = participant_one)
        $participantOneId = min($user->id, $target->id);
        $participantTwoId = max($user->id, $target->id);

        $conversation = Conversation::create([
            'participant_one_id' => $participantOneId,
            'participant_two_id' => $participantTwoId,
        ]);

        $conversation->load(['participantOne:id,name,avatar_url,logo_url,role', 'participantTwo:id,name,avatar_url,logo_url,role']);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $user),
            'is_new'       => true,
        ], 201);
    }

    /**
     * Get a single conversation with full details.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$conversation->hasParticipant($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $user),
        ]);
    }

    /**
     * Format a conversation for API response.
     */
    private function formatConversation(Conversation $conversation, User $user): array
    {
        $other = $conversation->otherParticipant($user);

        return [
            'id'               => $conversation->id,
            'other_participant' => $other ? [
                'id'         => $other->id,
                'name'       => $other->name,
                'avatar_url' => $other->avatar_url ?? $other->logo_url ?? null,
                'role'       => $other->role,
            ] : null,
            'unread_count'    => $conversation->unreadCountFor($user),
            'is_blocked'      => $conversation->isBlocked(),
            'blocked_by_me'   => $conversation->isBlockedBy($user),
            'last_message_at' => $conversation->last_message_at,
            'created_at'      => $conversation->created_at,
        ];
    }
}

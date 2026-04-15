<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_one_id',
        'participant_two_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function participantOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_one_id');
    }

    public function participantTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderByDesc('created_at');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ConversationBlock::class);
    }

    /**
     * Get the latest message (for sidebar preview).
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Get the other participant relative to the given user.
     */
    public function otherParticipant(User $user): ?User
    {
        if ($user->id === $this->participant_one_id) {
            return $this->participantTwo;
        }

        return $this->participantOne;
    }

    /**
     * Check if the given user is a participant of this conversation.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participant_one_id === $user->id
            || $this->participant_two_id === $user->id;
    }

    /**
     * Check if the conversation has any active block.
     */
    public function isBlocked(): bool
    {
        return $this->blocks()->exists();
    }

    /**
     * Check if a specific user has blocked this conversation.
     */
    public function isBlockedBy(User $user): bool
    {
        return $this->blocks()->where('blocker_id', $user->id)->exists();
    }

    /**
     * Find an existing conversation between two users (regardless of order).
     */
    public static function between(int $userA, int $userB): ?self
    {
        return static::where(function ($q) use ($userA, $userB) {
            $q->where('participant_one_id', $userA)
              ->where('participant_two_id', $userB);
        })->orWhere(function ($q) use ($userA, $userB) {
            $q->where('participant_one_id', $userB)
              ->where('participant_two_id', $userA);
        })->first();
    }

    /**
     * Count unread messages for a given user in this conversation.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();
    }
}

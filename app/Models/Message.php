<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'is_read',
        'is_edited',
    ];

    protected function casts(): array
    {
        return [
            'is_read'   => 'boolean',
            'is_edited' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Check if this message is the last one sent by its sender in the conversation.
     * Only the last message can be edited.
     */
    public function isLastBySender(): bool
    {
        $lastMessage = Message::where('conversation_id', $this->conversation_id)
            ->where('sender_id', $this->sender_id)
            ->orderByDesc('created_at')
            ->first();

        return $lastMessage && $lastMessage->id === $this->id;
    }
}

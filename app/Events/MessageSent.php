<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'              => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id'       => $this->message->sender_id,
                'body'            => $this->message->body,
                'is_read'         => $this->message->is_read,
                'is_edited'       => $this->message->is_edited,
                'created_at'      => $this->message->created_at->toISOString(),
                'updated_at'      => $this->message->updated_at->toISOString(),
                'sender'          => [
                    'id'         => $this->message->sender->id,
                    'name'       => $this->message->sender->name,
                    'avatar_url' => $this->message->sender->avatar_url ?? $this->message->sender->logo_url ?? null,
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Conversation $conversation,
        private readonly Message $message,
        private readonly User $sender
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        $preview = Str::limit(trim((string) $this->message->body), 140, '...');

        return NotificationPayload::make(
            type: 'chat.new_message',
            title: 'New message from '.$this->sender->name,
            body: $preview,
            severity: 'info',
            actionUrl: '/messages/'.$this->conversation->id,
            entity: [
                'type' => 'conversation',
                'id' => $this->conversation->id,
            ],
            meta: [
                'conversation_id' => $this->conversation->id,
                'message_id' => $this->message->id,
                'sender_id' => $this->sender->id,
                'sender_name' => $this->sender->name,
                'preview' => $preview,
            ],
        );
    }
}

<?php

namespace App\Notifications;

use App\Support\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public string $severity = "info",
        public array $data = []
    ) {}

    public function via($notifiable)
    {
        return ["database", "broadcast"];
    }

    public function toDatabase($notifiable)
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->payload());
    }

    protected function payload()
    {
        return NotificationPayload::make(
            type: $this->type,
            title: $this->title,
            body: $this->body,
            severity: $this->severity,
            actionUrl: $this->actionUrl,
            entity: $this->data,
            meta: $this->data
        );
    }
}

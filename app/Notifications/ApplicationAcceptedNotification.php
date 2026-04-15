<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Postulation;
use App\Support\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Postulation $postulation)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
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

    public function toMail(object $notifiable): MailMessage
    {
        $offreTitle = (string) ($this->postulation->offre?->title ?? 'your offer');

        return (new MailMessage())
            ->subject('Application accepted: '.$offreTitle)
            ->line('Your application was accepted by the enterprise.')
            ->line('You can now start the conversation from your messages.')
            ->action('Open Applications', url('/my-applications'));
    }

    private function payload(): array
    {
        $offre = $this->postulation->offre;

        return NotificationPayload::make(
            type: 'application.accepted',
            title: 'Application accepted',
            body: 'Your application for '.$offre?->title.' was accepted.',
            severity: 'info',
            actionUrl: '/my-applications',
            entity: [
                'type' => 'offre',
                'id' => $offre?->id,
                'title' => $offre?->title,
            ],
            meta: [
                'postulation_id' => $this->postulation->id,
                'offre_id' => $offre?->id,
                'status' => 'accepted',
                'chat_unlocked' => true,
            ],
        );
    }
}

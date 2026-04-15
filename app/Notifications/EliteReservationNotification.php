<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Offre;
use App\Support\NotificationPayload;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EliteReservationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Offre $offer,
        private readonly int $matchPercentage,
        private readonly CarbonInterface $expiresAt
    ) {
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
        return (new MailMessage())
            ->subject('Elite reservation available for '.$this->offer->title)
            ->line('Your profile matches this offer at '.$this->matchPercentage.'%.')
            ->line('A VIP reservation is held for you until '.$this->expiresAt->toDateTimeString().'.')
            ->action('View Offer', url('/offres/'.$this->offer->id));
    }

    private function payload(): array
    {
        return NotificationPayload::make(
            type: 'vip.elite_reservation',
            title: 'Elite reservation confirmed',
            body: 'You secured a guaranteed reservation for '.$this->offer->title.'.',
            severity: 'info',
            actionUrl: '/offres/'.$this->offer->id,
            entity: [
                'type' => 'offre',
                'id' => $this->offer->id,
                'title' => $this->offer->title,
            ],
            meta: [
                'offre_id' => $this->offer->id,
                'offre_title' => $this->offer->title,
                'match_percentage' => $this->matchPercentage,
                'expires_at' => $this->expiresAt->toISOString(),
            ],
        );
    }
}

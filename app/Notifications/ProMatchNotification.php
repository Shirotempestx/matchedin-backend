<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Offre;
use App\Support\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProMatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Offre $offer,
        private readonly int $matchPercentage
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
        return NotificationPayload::make(
            type: 'vip.pro_match',
            title: 'High compatibility offer',
            body: $this->offer->title.' matches your profile at '.$this->matchPercentage.'%.',
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
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OfferReservation;
use App\Models\Offre;
use App\Models\User;
use App\Notifications\EliteReservationNotification;
use App\Notifications\ProMatchNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VipAllocationService
{
    public function allocateReservations(Offre $offer): void
    {
        $positionsAvailable = (int) ($offer->positions_available ?? $offer->places_demanded ?? 0);

        // Reserve exactly 50% of the available positions for Elite users.
        $maxReservations = max(0, (int) floor($positionsAvailable * 0.5));

        /** @var Collection<int, array{user: User, score: int}> $matchedCandidates */
        $matchedCandidates = User::query()
            ->whereIn('role', ['student', 'Etudiant'])
            ->get()
            ->map(function (User $user) use ($offer): array {
                return [
                    'user' => $user,
                    'score' => $offer->calculateMatchPercentage($user),
                ];
            })
            ->filter(static fn (array $candidate): bool => $candidate['score'] >= 90)
            ->values();

        $eliteCandidates = $matchedCandidates
            ->filter(static fn (array $candidate): bool => strtolower((string) ($candidate['user']->subscription_tier ?? '')) === 'elite')
            ->sort(static function (array $left, array $right): int {
                // Tie-breaker: highest exact compatibility first, then user id for stable ordering.
                return ($right['score'] <=> $left['score']) ?: ($left['user']->id <=> $right['user']->id);
            })
            ->values();

        $proCandidates = $matchedCandidates
            ->filter(static fn (array $candidate): bool => strtolower((string) ($candidate['user']->subscription_tier ?? '')) === 'pro')
            ->values();

        $expiresAt = Carbon::now()->addDay();

        $reservedElite = $eliteCandidates->take($maxReservations)->values();
        $remainingElite = $eliteCandidates->slice($reservedElite->count())->values();

        foreach ($reservedElite as $candidate) {
            $reservation = OfferReservation::query()->updateOrCreate(
                [
                    'user_id' => $candidate['user']->id,
                    'job_offer_id' => $offer->id,
                ],
                [
                    'expires_at' => $expiresAt,
                    'status' => OfferReservation::STATUS_PENDING,
                ]
            );

            $candidate['user']->notify(new EliteReservationNotification(
                $offer,
                $candidate['score'],
                $reservation->expires_at ?? $expiresAt
            ));
        }

        $standardRecipients = $remainingElite->merge($proCandidates)->values();

        foreach ($standardRecipients as $candidate) {
            $candidate['user']->notify(new ProMatchNotification($offer, $candidate['score']));
        }
    }
}

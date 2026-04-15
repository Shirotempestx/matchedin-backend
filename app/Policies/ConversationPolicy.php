<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Offre;
use App\Models\Postulation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine if the initiator can start a conversation with the target.
     *
     * Authorization Matrix:
     * ┌──────────────────────┬────────────────────────────────────────────┐
     * │ Initiator            │ Rule                                      │
     * ├──────────────────────┼────────────────────────────────────────────┤
     * │ Enterprise (any)     │ Can message ANY Individual                │
     * │ Elite Individual     │ Can message ANY Enterprise                │
     * │ Pro Individual       │ Shared Offre with ≥75% match             │
     * │ Free Individual      │ Accepted candidacy OR enterprise first   │
     * └──────────────────────┴────────────────────────────────────────────┘
     */
    public function create(User $initiator, User $target): bool
    {
        // Cannot message yourself
        if ($initiator->id === $target->id) {
            return false;
        }

        // Must be cross-role (enterprise ↔ student)
        if ($initiator->role === $target->role) {
            return false;
        }

        // ── Enterprise initiating → always allowed ──
        if ($initiator->isEnterprise()) {
            return true;
        }

        // ── Student initiating → depends on tier ──
        if ($initiator->isStudent()) {
            $tier = $initiator->subscription_tier;

            // Elite → unrestricted access to any enterprise
            if ($tier === 'elite') {
                return true;
            }

            // Pro → needs at least one shared Offre with ≥75% compatibility
            if ($tier === 'pro') {
                return $this->hasHighMatchOffre($initiator, $target, 75);
            }

            // Free → either accepted candidacy OR enterprise initiated first
            return $this->hasAcceptedCandidacy($initiator, $target)
                || $this->enterpriseInitiatedFirst($initiator, $target);
        }

        return false;
    }

    /**
     * Check if the student has any accepted application for an offer owned by the enterprise.
     */
    private function hasAcceptedCandidacy(User $student, User $enterprise): bool
    {
        return Postulation::where('user_id', $student->id)
            ->where('status', 'accepted')
            ->whereHas('offre', function ($query) use ($enterprise) {
                $query->where('user_id', $enterprise->id);
            })
            ->exists();
    }

    /**
     * Check if the enterprise already initiated a conversation with this student.
     * This covers the case where the enterprise contacted the student about a pending application.
     */
    private function enterpriseInitiatedFirst(User $student, User $enterprise): bool
    {
        // If a conversation already exists between them, the enterprise must have started it
        $conversation = Conversation::between($student->id, $enterprise->id);

        if (!$conversation) {
            return false;
        }

        // Check if the first message in the conversation was from the enterprise
        $firstMessage = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->first();

        return $firstMessage && $firstMessage->sender_id === $enterprise->id;
    }

    /**
     * Check if the student has a match score ≥ threshold on any offer owned by the enterprise.
     */
    private function hasHighMatchOffre(User $student, User $enterprise, int $threshold): bool
    {
        $offres = Offre::where('user_id', $enterprise->id)
            ->where('is_active', true)
            ->get();

        foreach ($offres as $offre) {
            if ($offre->calculateMatchPercentage($student) >= $threshold) {
                return true;
            }
        }

        return false;
    }
}

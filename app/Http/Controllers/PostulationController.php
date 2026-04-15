<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
use App\Models\Offre;
use App\Models\Postulation;
use App\Mail\PostulationStatusChanged;
use App\Mail\NewApplicationReceived;
use App\Mail\PostulationConfirmation;
use App\Notifications\ApplicationAcceptedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PostulationController extends Controller
{
    /**
     * Submit a new application (Student only).
     */
    public function store(Request $request)
    {
        $request->validate([
            'offre_id' => 'required|exists:offres,id',
            'message'  => 'nullable|string|max:1000',
        ]);

        if ($request->user()->role !== 'student') {
            return response()->json(['message' => __('messages.only_students_can_apply')], 403);
        }

        // Check if already applied
        $exists = Postulation::where('user_id', $request->user()->id)
            ->where('offre_id', $request->offre_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => __('messages.already_applied')], 422);
        }

        $postulation = Postulation::create([
            'user_id'  => $request->user()->id,
            'offre_id' => $request->offre_id,
            'message'  => $request->message,
        ]);

        // Notify the enterprise
        $entreprise = $postulation->offre->user;
        if ($entreprise && $entreprise->email) {
            Mail::to($entreprise->email)
                ->locale($entreprise->preferred_language ?? 'fr')
                ->send(new NewApplicationReceived($postulation));
        }

        if ($entreprise) {
            InAppNotification::create([
                'user_id' => $entreprise->id,
                'type' => 'new_applicant',
                'title' => 'Nouvelle candidature',
                'body' => "{$request->user()->name} a postule a l'offre {$postulation->offre->title}.",
                'action_url' => '/explore-candidates',
                'severity' => 'info',
                'data' => [
                    'postulation_id' => $postulation->id,
                    'offre_id' => $postulation->offre_id,
                    'student_id' => $request->user()->id,
                ],
            ]);

            $matchPercentage = $postulation->offre->calculateMatchPercentage($request->user());
            if ($matchPercentage >= 90) {
                InAppNotification::create([
                    'user_id' => $entreprise->id,
                    'type' => 'strong_candidate',
                    'title' => 'Candidat fort detecte',
                    'body' => "{$request->user()->name} correspond a {$matchPercentage}% sur {$postulation->offre->title}.",
                    'action_url' => '/explore-candidates',
                    'severity' => 'warning',
                    'data' => [
                        'match_percentage' => $matchPercentage,
                        'postulation_id' => $postulation->id,
                        'offre_id' => $postulation->offre_id,
                        'student_id' => $request->user()->id,
                    ],
                ]);
            }
        }

        // Notify the student (Confirmation)
        Mail::to($request->user()->email)
            ->locale($request->user()->preferred_language ?? 'fr')
            ->send(new PostulationConfirmation($postulation));

        return response()->json([
            'message' => __('messages.application_sent'),
            'postulation' => $postulation
        ], 201);
    }

    /**
     * List current student's applications.
     */
    public function myApplications(Request $request)
    {
        $applications = $request->user()->postulations()
            ->with('offre.user:id,company_name,industry')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($applications);
    }

    /**
     * List all applications for all offers owned by the recruiter.
     */
    public function indexCandidates(Request $request)
    {
        $offresIds = $request->user()->offres()->pluck('id');

        $query = Postulation::whereIn('offre_id', $offresIds)
            ->with(['user', 'offre:id,title']);

        if ($request->has('status') && in_array($request->status, ['accepted', 'rejected', 'pending'])) {
            $query->where('status', $request->status);
        }

        $applications = $query->orderByDesc('created_at')
            ->paginate(15);

        $applications->getCollection()->transform(function ($app) {
            if ($app->user && $app->offre) {
                $app->setAttribute('match_percentage', $app->offre->calculateMatchPercentage($app->user));
                $app->user->setAttribute('slug', Str::slug((string) $app->user->name));
            }
            return $app;
        });

        return response()->json($applications);
    }

    /**
     * List applications for an offer (Enterprise owner only).
     */
    public function forOffre(Request $request, Offre $offre)
    {
        if ($offre->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        $applications = $offre->postulations()
            ->with('user:id,name,email,profile_picture,role')
            ->orderByDesc('created_at')
            ->paginate(10);

        $applications->getCollection()->transform(function ($app) use ($offre) {
            if ($app->user) {
                $app->setAttribute('match_percentage', $offre->calculateMatchPercentage($app->user));
            }
            return $app;
        });

        return response()->json($applications);
    }

    /**
     * Update application status (Enterprise owner only).
     */
    public function updateStatus(Request $request, Postulation $postulation)
    {
        $offre = $postulation->offre;
        $previousStatus = (string) $postulation->status;

        if ($offre->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        $request->validate([
            'status' => 'required|in:accepted,rejected,pending',
        ]);

        $postulation->update(['status' => $request->status]);

        $student = $postulation->user;
        if (!$student) {
            return response()->json([
                'message' => __('messages.application_status_updated'),
                'postulation' => $postulation,
            ]);
        }

        if ($request->status === 'accepted' && $previousStatus !== 'accepted') {
            $postulation->loadMissing('offre');
            $student->notify(new ApplicationAcceptedNotification($postulation));
        } else {
            if (!empty($student->email)) {
                Mail::to($student->email)
                    ->locale($student->preferred_language ?? 'fr')
                    ->send(new PostulationStatusChanged($postulation));
            }

            InAppNotification::create([
                'user_id' => $student->id,
                'type' => 'application_status_update',
                'title' => 'Statut de candidature mis a jour',
                'body' => "Votre candidature pour {$offre->title} est maintenant: {$request->status}.",
                'action_url' => '/my-applications',
                'severity' => $request->status === 'accepted' ? 'info' : 'warning',
                'data' => [
                    'postulation_id' => $postulation->id,
                    'offre_id' => $offre->id,
                    'status' => $request->status,
                ],
            ]);
        }

        return response()->json([
            'message' => __('messages.application_status_updated'),
            'postulation' => $postulation
        ]);
    }
}

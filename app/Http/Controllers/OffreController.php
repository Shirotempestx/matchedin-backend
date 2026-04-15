<?php

namespace App\Http\Controllers;

use App\Events\OfferPublished;
use App\Models\InAppNotification;
use App\Models\Offre;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OffreController extends Controller
{
    /**
     * List all active offres (public).
     */
    public function index(Request $request)
    {
        $query = Offre::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->with('user:id,name,company_name,industry,company_size');

        // Optional search by text (title or description)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Optional filter by company name
        if ($request->filled('company')) {
            $company = $request->input('company');
            $query->whereHas('user', function($q) use ($company) {
                $q->where('company_name', 'like', "%{$company}%")
                  ->orWhere('name', 'like', "%{$company}%");
            });
        }

        // Optional filter by location
        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->input('location') . '%');
        }

        // Optional filter by multiple work_modes (e.g., ?work_mode[]=Remote&work_mode[]=Hybrid)
        if ($request->has('work_mode')) {
            $workModes = is_array($request->input('work_mode')) ? $request->input('work_mode') : explode(',', $request->input('work_mode'));
            if (!empty($workModes)) {
                $query->whereIn('work_mode', $workModes);
            }
        }

        // Optional filter by multiple contract_types
        if ($request->has('contract_type')) {
            $contractTypes = is_array($request->input('contract_type')) ? $request->input('contract_type') : explode(',', $request->input('contract_type'));
            if (!empty($contractTypes)) {
                $query->whereIn('contract_type', $contractTypes);
            }
        }

        // Optional filter by salary range
        if ($request->filled('salary_min')) {
            $query->where('salary_min', '>=', $request->input('salary_min'));
        }
        if ($request->filled('salary_max')) {
            $query->where('salary_max', '<=', $request->input('salary_max'));
        }

        // Optional filter by specific industry (through user relationship)
        if ($request->filled('industry')) {
            $industry = $request->input('industry');
            $query->whereHas('user', function($q) use ($industry) {
                $q->where('industry', 'like', "%{$industry}%");
            });
        }

        $user = $request->user('sanctum');
        if ($user && $user->role === 'student') {
            $allOffres = $query->get();

            // Calculate match percentage and sort descending
            $allOffres = $allOffres->map(function ($offre) use ($user) {     
                if ($offre->user) {
                    $offre->user->setAttribute('slug', Str::slug((string) ($offre->user->company_name ?? $offre->user->name)));
                }
                $offre->setAttribute('match_percentage', $offre->calculateMatchPercentage($user));
                return $offre;
            });

            if ($request->filled('min_match')) {
                $minMatch = (int) $request->input('min_match');
                $allOffres = $allOffres->filter(function($offre) use ($minMatch) {
                    return $offre->match_percentage >= $minMatch;
                });
            }

            $allOffres = $allOffres->sortByDesc('match_percentage')->values();

            // Manual pagination
            $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
            $perPage = 12;
            $items = $allOffres->slice(($page - 1) * $perPage, $perPage)->values();

            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(    
                $items,
                $allOffres->count(),
                $perPage,
                $page,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
            );

            return response()->json($paginator);
        }

        // Default sorting for non-students
        $query->orderByDesc('created_at');
        $offres = $query->paginate(12);
        $offres->getCollection()->transform(function ($offre) {
            if ($offre->user) {
                $offre->user->setAttribute('slug', Str::slug((string) ($offre->user->company_name ?? $offre->user->name)));
            }
            return $offre;
        });

        return response()->json($offres);
    }

    /**
     * Create a new offre (enterprise only).
     */
    public function store(Request $request)
    {
        // Only enterprise users can create offres
        if ($request->user()->role !== 'enterprise') {
            return response()->json(['message' => 'Seules les entreprises peuvent publier des offres.'], 403);
        }

        // Must be approved by admin
        if ($request->user()->status !== 'active') {
            return response()->json(['message' => 'Votre compte doit être approuvé par un administrateur pour publier des offres.'], 403);
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['required', 'string'],
            'location'        => ['nullable', 'string', 'max:255'],
            'work_mode'       => ['required', 'in:Remote,Hybrid,On-site'],   
            'salary_min'      => ['nullable', 'integer', 'min:0'],
            'salary_max'      => ['nullable', 'integer', 'min:0'],
            'contract_type'   => ['required', 'in:CDI,CDD,Stage,Freelance'], 
            'skills_required' => ['nullable', 'array'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'internship_period' => ['nullable', 'integer', 'min:1'],
            'niveau_etude'    => ['nullable', 'string', 'max:255'],
            'places_demanded' => ['required', 'integer', 'min:1'],
        ]);

        if (empty($request->user()->subscription_tier) && $validated['places_demanded'] > 5) {
            return response()->json(['message' => 'Le nombre de places est limité à 5 pour le plan gratuit. Veuillez passer à un abonnement Premium.', 'require_premium' => true], 403);
        }

        $offre = $request->user()->offres()->create($validated);
        OfferPublished::dispatch($offre);

        // Notify admins that a new offer is pending review
        $admins = User::query()->whereIn('role', ['Admin', 'admin'])->get(['id']);
        foreach ($admins as $admin) {
            $this->dispatchInAppNotification([
                'user_id' => $admin->id,
                'type' => 'pending_offer',
                'title' => 'Nouvelle offre en attente',
                'body' => "L'offre {$offre->title} est en attente de validation.",
                'action_url' => '/admin/offres',
                'severity' => 'critical',
                'data' => [
                    'offre_id' => $offre->id,
                    'enterprise_id' => $request->user()->id,
                ],
            ]);
        }

        // Notify students who follow this enterprise
        $followers = $request->user()->followers()->get(['users.id', 'users.name']);
        foreach ($followers as $follower) {
            $this->dispatchInAppNotification([
                'user_id' => $follower->id,
                'type' => 'offer_from_followed_enterprise',
                'title' => 'Nouvelle offre d\'une entreprise suivie',
                'body' => "{$request->user()->company_name} a publie {$offre->title}.",
                'action_url' => "/offres/{$offre->id}",
                'severity' => 'info',
                'data' => [
                    'offre_id' => $offre->id,
                    'enterprise_id' => $request->user()->id,
                ],
            ]);
        }

        // Notify students with strong match (>= 90%)
        $students = User::query()->whereIn('role', ['student', 'Etudiant'])->get();
        foreach ($students as $student) {
            $match = $offre->calculateMatchPercentage($student);
            if ($match < 90) {
                continue;
            }

            $this->dispatchInAppNotification([
                'user_id' => $student->id,
                'type' => 'high_match_offer',
                'title' => 'Offre tres compatible',
                'body' => "{$offre->title} correspond a {$match}% avec votre profil.",
                'action_url' => "/offres/{$offre->id}",
                'severity' => 'warning',
                'data' => [
                    'offre_id' => $offre->id,
                    'enterprise_id' => $request->user()->id,
                    'match_percentage' => $match,
                ],
            ]);
        }

        return response()->json(['message' => __('messages.offer_created'), 'offre' => $offre], 201);
    }

    /**
     * Show a single offre (public).
     */
    public function show($id, Request $request)
    {
        $offre = Offre::with('user:id,name,company_name,industry,company_size,website')
            ->findOrFail($id);

        if ($offre->user) {
            $offre->user->setAttribute('slug', Str::slug((string) ($offre->user->company_name ?? $offre->user->name)));
        }

        $user = $request->user('sanctum');
        if ($user && $user->role === 'student') {
            $offre->setAttribute('match_percentage', $offre->calculateMatchPercentage($user));
        }

        return response()->json($offre);
    }

    /**
     * List offers belonging to the authenticated user.
     */
    public function myOffres(Request $request)
    {
        $offres = $request->user()->offres()
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($offres);
    }

    /**
     * Update an existing offre.
     */
    public function update(Request $request, Offre $offre)
    {
        if ($offre->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['required', 'string'],
            'location'        => ['nullable', 'string', 'max:255'],
            'work_mode'       => ['required', 'in:Remote,Hybrid,On-site'],   
            'salary_min'      => ['nullable', 'integer', 'min:0'],
            'salary_max'      => ['nullable', 'integer', 'min:0'],
            'contract_type'   => ['required', 'in:CDI,CDD,Stage,Freelance'], 
            'skills_required' => ['nullable', 'array'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'internship_period' => ['nullable', 'integer', 'min:1'],
            'niveau_etude'    => ['nullable', 'string', 'max:255'],
            'places_demanded' => ['required', 'integer', 'min:1'],
        ]);

        if (empty($request->user()->subscription_tier) && $validated['places_demanded'] > 5) {
            return response()->json(['message' => 'Le nombre de places est limité à 5 pour le plan gratuit. Veuillez passer à un abonnement Premium.', 'require_premium' => true], 403);
        }

        $offre->update($validated);

        return response()->json(['message' => __('messages.offer_updated'), 'offre' => $offre]);
    }

    /**
     * Delete an offre.
     */
    public function destroy(Request $request, Offre $offre)
    {
        if ($offre->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        $offre->delete();

        return response()->json(['message' => __('messages.offer_deleted')]);
    }

    /**
     * Republish an expired or soon-to-expire offre.
     */
    public function republish(Request $request, Offre $offre)
    {
        if ($offre->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        $offre->update([
            'end_date' => now()->addDays(30),
            'is_active' => true,
        ]);

        return response()->json(['message' => __('messages.offer_republished'), 'offre' => $offre]);
    }

    /**
     * Increment view count for an offre.
     */
    public function incrementView(Offre $offre)
    {
        $offre->increment('views_count');
        return response()->json(['message' => __('messages.view_incremented'), 'views_count' => $offre->views_count]);
    }

    /**
     * Best-effort notification dispatch: never block offer creation.
     */
    private function dispatchInAppNotification(array $payload): void
    {
        if (!Schema::hasTable('in_app_notifications')) {
            return;
        }

        try {
            InAppNotification::create($payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to create in-app notification from OffreController', [
                'message' => $e->getMessage(),
                'notification_type' => $payload['type'] ?? null,
                'target_user_id' => $payload['user_id'] ?? null,
            ]);
        }
    }
}

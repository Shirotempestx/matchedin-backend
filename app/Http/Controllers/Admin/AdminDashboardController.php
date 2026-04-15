<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Offre;
use App\Models\Postulation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function getStats()
    {
        $usersCount = User::whereNotIn('role', ['Admin', 'admin'])->count();
        $studentsCount = User::where('role', 'student')->orWhere('role', 'Etudiant')->count();
        $enterprisesCount = User::where('role', 'enterprise')->orWhere('role', 'Entreprise')->count();

        $activeOffers = Offre::where('validation_status', 'approved')->count();
        $pendingOffers = Offre::where('validation_status', 'pending')->orWhereNull('validation_status')->count();

        $totalApplications = Postulation::count();

        $topConsultedOffers = Offre::with('user:id,name,company_name')
            ->orderByDesc('views_count')
            ->limit(5)
            ->get();

        // Registration history (last 7 days)
        $registrationsEvol = User::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('role', '!=', 'Admin')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->limit(7)
            ->get();

        // Offers by Sector (Contract Type)
        $offersBySector = Offre::select('contract_type as sector', DB::raw('count(*) as count'))
            ->groupBy('contract_type')
            ->get();

        // Activity Feed (combine latets users, offers, and reports)
        $latestUsers = User::orderByDesc('created_at')->limit(3)->get()->map(function ($u) {
            return [
                'type' => 'user',
                'title' => 'Nouvel Utilisateur',
                'description' => "{$u->name} ({$u->role}) a rejoint la plateforme.",
                'date' => $u->created_at,
            ];
        });

        $latestOffers = Offre::orderByDesc('created_at')->limit(3)->get()->map(function ($o) {
            return [
                'type' => 'offer',
                'title' => 'Nouvelle Offre',
                'description' => "L'offre '{$o->title}' a été publiée et est en attente de validation.",
                'date' => $o->created_at,
            ];
        });

        $activityFeed = $latestUsers->concat($latestOffers)
            ->sortByDesc('date')
            ->take(5)
            ->values();

        return response()->json([
            'users' => [
                'total' => $usersCount,
                'students' => $studentsCount,
                'enterprises' => $enterprisesCount,
            ],
            'offers' => [
                'active' => $activeOffers,
                'pending' => $pendingOffers,
                'top_consulted' => $topConsultedOffers,
            ],
            'offers_by_sector' => $offersBySector,
            'platform' => [
                'average_match_score' => collect($topConsultedOffers)->avg('views_count') ?? 0, // Mock score for now
            ],
            'registrations_evolution' => $registrationsEvol,
            'activity_feed' => $activityFeed->map(function($item) {
                $carbon = \Carbon\Carbon::parse($item['date']);
                $item['date'] = $carbon->format('d/m/Y');
                $item['time'] = $carbon->format('H:i');
                return $item;
            }),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Models\Offre;
use Illuminate\Http\Request;

class AdminOffreController extends Controller
{
    public function index(Request $request)
    {
        $query = Offre::with('user:id,name,company_name');

        if ($request->has('validation_status') && $request->validation_status !== 'all') {
            $query->where('validation_status', $request->validation_status);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->whereRaw('LOWER(company_name) LIKE ?', ["%{$search}%"]);
                  });
            });
        }

        if ($request->has('export') && $request->export === 'true') {
            $offres = $query->orderByDesc('created_at')->get();
            return response()->json($offres);
        }

        $offres = $query->orderByDesc('created_at')->paginate(10);

        return response()->json($offres);
    }

    public function validateOffre(Request $request, Offre $offre)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,pending'
        ]);

        $offre->update(['validation_status' => $request->status]);

        InAppNotification::create([
            'user_id' => $offre->user_id,
            'type' => $request->status === 'approved' ? 'offer_approved' : 'offer_rejected',
            'title' => $request->status === 'approved' ? 'Offre approuvee' : 'Offre rejetee',
            'body' => "Votre offre {$offre->title} est maintenant: {$offre->validation_status}.",
            'action_url' => '/dashboard',
            'severity' => $request->status === 'approved' ? 'info' : 'warning',
            'data' => [
                'offre_id' => $offre->id,
                'status' => $offre->validation_status,
            ],
        ]);

        return response()->json(['message' => "Offer status updated to {$offre->validation_status}", 'offre' => $offre]);
    }
}

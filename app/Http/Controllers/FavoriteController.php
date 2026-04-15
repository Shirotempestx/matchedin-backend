<?php

namespace App\Http\Controllers;

use App\Models\Offre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Get user's favorite opportunities.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $favorites = $user->favorites()->with('offre.user')->get()->pluck('offre');
        
        return response()->json($favorites);
    }

    /**
     * Toggle an opportunity in favorites.
     */
    public function toggle(Request $request, $offre_id)
    {
        $user = Auth::user();
        $offre = Offre::findOrFail($offre_id);

        if ($user->favorites()->where('offre_id', $offre->id)->exists()) {
            $user->favorites()->where('offre_id', $offre->id)->delete();
            return response()->json(['status' => 'removed']);
        } else {
            $user->favorites()->create(['offre_id' => $offre->id]);
            return response()->json(['status' => 'added']);
        }
    }
}

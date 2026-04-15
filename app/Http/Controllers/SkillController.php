<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    /**
     * Search for skills by name and type(category).
     */
    public function search(Request $request)
    {
        $query = $request->query('q');
        $type = $request->query('type'); // 'IT' or 'NON_IT'

        if (!$query) {
            // Return top weighted skills by default
            $skills = Skill::when($type, function ($q) use ($type) {
                    return $q->where('category', $type);
                })
                ->orderByDesc('weight')
                ->limit(50)
                ->get();
        } else {
            // Search with case-insensitive matching
            $skills = Skill::where(function ($q) use ($query) {
                    $q->where('nom_competence', 'LIKE', "%{$query}%")
                      ->orWhereRaw('LOWER(nom_competence) LIKE ?', ["%" . strtolower($query) . "%"]);
                })
                ->when($type, function ($q) use ($type) {
                    return $q->where('category', $type);
                })
                ->limit(15)
                ->get();
        }

        // Map backend names to frontend expected names
        $mappedSkills = $skills->map(function ($skill) {
            return [
                'id' => $skill->id_competence,
                'name' => $skill->nom_competence,
                'category' => $skill->category,
                'weight' => $skill->weight,
            ];
        });

        return response()->json($mappedSkills);
    }
}

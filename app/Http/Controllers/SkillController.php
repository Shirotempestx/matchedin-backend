<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SkillController extends Controller
{
    /**
     * Search for skills by name and type(category).
     */
    public function search(Request $request)
    {
        $query = $request->query('q');
        $type = $request->query('type'); // 'IT' or 'NON_IT'
        $hasCategory = Schema::hasColumn('Competences', 'category');
        $hasWeight = Schema::hasColumn('Competences', 'weight');

        if (!$query) {
            // Return top weighted skills by default
            $skills = Skill::when($type && $hasCategory, function ($q) use ($type) {
                    return $q->where('category', $type);
                })
                ->when($hasWeight, function ($q) {
                    return $q->orderByDesc('weight');
                }, function ($q) {
                    return $q->orderBy('id_competence');
                })
                ->limit(50)
                ->get();
        } else {
            // Search with case-insensitive matching
            $skills = Skill::where(function ($q) use ($query) {
                    $q->where('nom_competence', 'LIKE', "%{$query}%")
                      ->orWhereRaw('LOWER(nom_competence) LIKE ?', ["%" . strtolower($query) . "%"]);
                })
                ->when($type && $hasCategory, function ($q) use ($type) {
                    return $q->where('category', $type);
                })
                ->limit(15)
                ->get();
        }

        // Map backend names to frontend expected names
        $mappedSkills = $skills->map(function ($skill) use ($hasCategory, $hasWeight) {
            return [
                'id' => $skill->id_competence,
                'name' => $skill->nom_competence,
                'category' => $hasCategory ? ($skill->category ?? 'IT') : 'IT',
                'weight' => $hasWeight ? ($skill->weight ?? 1) : 1,
            ];
        });

        return response()->json($mappedSkills);
    }
}

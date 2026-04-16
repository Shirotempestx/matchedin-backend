<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReferenceController extends Controller
{
    // Retrieve all references for the admin settings page
    public function index()
    {
        return response()->json([
            'skills' => DB::table('Competences')->orderBy('nom_competence')->get(),
            'cities' => DB::table('Villes')->orderBy('nom_ville')->get(),
            'education_levels' => DB::table('Niveaux_Etude')->orderBy('libelle')->get(),
        ]);
    }

    // --- Skills Management ---
    public function storeSkill(Request $request)
    {
        $name = trim((string) $request->input('nom_competence', ''));
        $category = trim((string) $request->input('category', ''));
        $weight = (int) ($request->input('weight', 1));

        $request->merge([
            'nom_competence' => $name,
            'category' => $category,
            'weight' => $weight,
        ]);

        $request->validate([
            'nom_competence' => 'required|string|max:100|unique:Competences,nom_competence',
            'category' => 'nullable|string|max:50',
            'weight' => 'integer|min:1|max:10'
        ]);

        $id = DB::table('Competences')->insertGetId([
            'nom_competence' => $request->nom_competence,
            'category' => $request->category !== '' ? $request->category : 'IT',
            'weight' => $request->weight ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_competence');

        return response()->json(['message' => 'Compétence ajoutée', 'skill' => DB::table('Competences')->where('id_competence', $id)->first()]);
    }

    public function destroySkill($id)
    {
        DB::table('Competences')->where('id_competence', $id)->delete();
        return response()->json(['message' => 'Compétence supprimée']);
    }

    // --- Cities Management ---
    public function storeCity(Request $request)
    {
        $request->validate([
            'nom_ville' => 'required|string|max:100|unique:Villes,nom_ville',
            'code_postal' => 'nullable|string|max:10'
        ]);

        $id = DB::table('Villes')->insertGetId([
            'nom_ville' => $request->nom_ville,
            'code_postal' => $request->code_postal,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_ville');

        return response()->json(['message' => 'Ville ajoutée', 'city' => DB::table('Villes')->where('id_ville', $id)->first()]);
    }

    public function destroyCity($id)
    {
        DB::table('Villes')->where('id_ville', $id)->delete();
        return response()->json(['message' => 'Ville supprimée']);
    }

    // --- Education Management ---
    public function storeEducation(Request $request)
    {
        $request->validate([
            'libelle' => 'required|string|max:50|unique:Niveaux_Etude,libelle'
        ]);

        $id = DB::table('Niveaux_Etude')->insertGetId([
            'libelle' => $request->libelle,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_niveau');

        return response()->json(['message' => 'Niveau ajouté', 'education' => DB::table('Niveaux_Etude')->where('id_niveau', $id)->first()]);
    }

    public function destroyEducation($id)
    {
        DB::table('Niveaux_Etude')->where('id_niveau', $id)->delete();
        return response()->json(['message' => 'Niveau supprimé']);
    }
}

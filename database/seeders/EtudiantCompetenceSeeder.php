<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EtudiantCompetenceSeeder extends Seeder
{
    /**
     * Seed the Etudiant_Competence pivot table.
     * Assigns 3-5 skills to each student with mastery levels (1-5).
     */
    public function run(): void
    {
        $etudiants = DB::table('Etudiants')->pluck('id_etudiant', 'nom');
        $competences = DB::table('Competences')->pluck('id_competence', 'nom_competence');

        $data = [
            // Bennani (Ahmed) – Full-stack web
            ['id_etudiant' => $etudiants['Bennani'], 'id_competence' => $competences['PHP'],        'niveau_maitrise' => 4],
            ['id_etudiant' => $etudiants['Bennani'], 'id_competence' => $competences['Laravel'],    'niveau_maitrise' => 4],
            ['id_etudiant' => $etudiants['Bennani'], 'id_competence' => $competences['JavaScript'], 'niveau_maitrise' => 3],
            ['id_etudiant' => $etudiants['Bennani'], 'id_competence' => $competences['Docker'],     'niveau_maitrise' => 3],
            ['id_etudiant' => $etudiants['Bennani'], 'id_competence' => $competences['Git'],        'niveau_maitrise' => 5],

            // El Moussaoui (Fatima) – UX/UI & Front-end
            ['id_etudiant' => $etudiants['El Moussaoui'], 'id_competence' => $competences['React'],      'niveau_maitrise' => 4],
            ['id_etudiant' => $etudiants['El Moussaoui'], 'id_competence' => $competences['JavaScript'], 'niveau_maitrise' => 4],
            ['id_etudiant' => $etudiants['El Moussaoui'], 'id_competence' => $competences['Figma'],      'niveau_maitrise' => 5],
            ['id_etudiant' => $etudiants['El Moussaoui'], 'id_competence' => $competences['Communication'], 'niveau_maitrise' => 4],

            // Tazi (Omar) – Data Science / AI
            ['id_etudiant' => $etudiants['Tazi'], 'id_competence' => $competences['Python'],  'niveau_maitrise' => 5],
            ['id_etudiant' => $etudiants['Tazi'], 'id_competence' => $competences['SQL'],     'niveau_maitrise' => 4],
            ['id_etudiant' => $etudiants['Tazi'], 'id_competence' => $competences['Git'],     'niveau_maitrise' => 3],
            ['id_etudiant' => $etudiants['Tazi'], 'id_competence' => $competences['Gestion de projet'], 'niveau_maitrise' => 2],

            // Idrissi (Sara) – Mobile dev
            ['id_etudiant' => $etudiants['Idrissi'], 'id_competence' => $competences['React'],      'niveau_maitrise' => 4],
            ['id_etudiant' => $etudiants['Idrissi'], 'id_competence' => $competences['JavaScript'], 'niveau_maitrise' => 3],
            ['id_etudiant' => $etudiants['Idrissi'], 'id_competence' => $competences['Git'],        'niveau_maitrise' => 3],

            // Amrani (Youssef) – Back-end
            ['id_etudiant' => $etudiants['Amrani'], 'id_competence' => $competences['PHP'],     'niveau_maitrise' => 5],
            ['id_etudiant' => $etudiants['Amrani'], 'id_competence' => $competences['Laravel'], 'niveau_maitrise' => 5],
            ['id_etudiant' => $etudiants['Amrani'], 'id_competence' => $competences['SQL'],     'niveau_maitrise' => 4],
            ['id_etudiant' => $etudiants['Amrani'], 'id_competence' => $competences['Node.js'], 'niveau_maitrise' => 3],
            ['id_etudiant' => $etudiants['Amrani'], 'id_competence' => $competences['Travail d\'équipe'], 'niveau_maitrise' => 4],
        ];

        DB::table('Etudiant_Competence')->insert($data);
    }
}

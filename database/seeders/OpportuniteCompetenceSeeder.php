<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OpportuniteCompetenceSeeder extends Seeder
{
    /**
     * Seed the Opportunite_Competence pivot table.
     * Assigns 2-4 required skills to each opportunity with importance weights (1-10).
     */
    public function run(): void
    {
        $opportunites = DB::table('Opportunites')->pluck('id_opportunite', 'titre');
        $competences = DB::table('Competences')->pluck('id_competence', 'nom_competence');

        $data = [
            // Stage Développeur Full-Stack Laravel/React
            ['id_opportunite' => $opportunites['Stage Développeur Full-Stack Laravel/React'], 'id_competence' => $competences['PHP'],        'poids_critere' => 8],
            ['id_opportunite' => $opportunites['Stage Développeur Full-Stack Laravel/React'], 'id_competence' => $competences['Laravel'],    'poids_critere' => 9],
            ['id_opportunite' => $opportunites['Stage Développeur Full-Stack Laravel/React'], 'id_competence' => $competences['React'],      'poids_critere' => 7],
            ['id_opportunite' => $opportunites['Stage Développeur Full-Stack Laravel/React'], 'id_competence' => $competences['Git'],        'poids_critere' => 5],

            // Ingénieur DevOps
            ['id_opportunite' => $opportunites['Ingénieur DevOps'], 'id_competence' => $competences['Docker'],  'poids_critere' => 10],
            ['id_opportunite' => $opportunites['Ingénieur DevOps'], 'id_competence' => $competences['Git'],     'poids_critere' => 8],
            ['id_opportunite' => $opportunites['Ingénieur DevOps'], 'id_competence' => $competences['Python'],  'poids_critere' => 6],

            // Consultant Data Analyst
            ['id_opportunite' => $opportunites['Consultant Data Analyst'], 'id_competence' => $competences['Python'], 'poids_critere' => 9],
            ['id_opportunite' => $opportunites['Consultant Data Analyst'], 'id_competence' => $competences['SQL'],    'poids_critere' => 10],
            ['id_opportunite' => $opportunites['Consultant Data Analyst'], 'id_competence' => $competences['Communication'], 'poids_critere' => 5],

            // Stage Assistant Chef de Projet IT
            ['id_opportunite' => $opportunites['Stage Assistant Chef de Projet IT'], 'id_competence' => $competences['Gestion de projet'], 'poids_critere' => 9],
            ['id_opportunite' => $opportunites['Stage Assistant Chef de Projet IT'], 'id_competence' => $competences['Communication'],     'poids_critere' => 8],

            // Développeur Mobile React Native
            ['id_opportunite' => $opportunites['Développeur Mobile React Native'], 'id_competence' => $competences['React'],      'poids_critere' => 10],
            ['id_opportunite' => $opportunites['Développeur Mobile React Native'], 'id_competence' => $competences['JavaScript'], 'poids_critere' => 8],
            ['id_opportunite' => $opportunites['Développeur Mobile React Native'], 'id_competence' => $competences['Git'],        'poids_critere' => 5],

            // Hackathon FinTech Challenge 2026
            ['id_opportunite' => $opportunites['Hackathon FinTech Challenge 2026'], 'id_competence' => $competences['JavaScript'],       'poids_critere' => 6],
            ['id_opportunite' => $opportunites['Hackathon FinTech Challenge 2026'], 'id_competence' => $competences['Travail d\'équipe'], 'poids_critere' => 8],
        ];

        DB::table('Opportunite_Competence')->insert($data);
    }
}

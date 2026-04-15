<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompetenceSeeder extends Seeder
{
    /**
     * Seed the Competences table with tech and soft skills.
     */
    public function run(): void
    {
        $competences = [
            ['nom_competence' => 'PHP'],
            ['nom_competence' => 'Laravel'],
            ['nom_competence' => 'React'],
            ['nom_competence' => 'JavaScript'],
            ['nom_competence' => 'Python'],
            ['nom_competence' => 'SQL'],
            ['nom_competence' => 'Git'],
            ['nom_competence' => 'Docker'],
            ['nom_competence' => 'Figma'],
            ['nom_competence' => 'Communication'],
            ['nom_competence' => 'Travail d\'équipe'],
            ['nom_competence' => 'Gestion de projet'],
            ['nom_competence' => 'Java'],
            ['nom_competence' => 'Node.js'],
            ['nom_competence' => 'Angular'],
        ];

        DB::table('Competences')->insert($competences);
    }
}

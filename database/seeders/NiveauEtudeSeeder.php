<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NiveauEtudeSeeder extends Seeder
{
    /**
     * Seed the Niveaux_Etude table.
     */
    public function run(): void
    {
        $niveaux = [
            ['libelle' => 'Bac'],
            ['libelle' => 'Bac+2'],
            ['libelle' => 'Bac+3 (Licence)'],
            ['libelle' => 'Bac+5 (Master)'],
            ['libelle' => 'Doctorat'],
        ];

        DB::table('Niveaux_Etude')->insert($niveaux);
    }
}

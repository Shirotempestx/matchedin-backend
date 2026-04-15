<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VilleSeeder extends Seeder
{
    /**
     * Seed the Villes table with Moroccan cities.
     */
    public function run(): void
    {
        $villes = [
            ['nom_ville' => 'Casablanca',  'code_postal' => '20000'],
            ['nom_ville' => 'Rabat',       'code_postal' => '10000'],
            ['nom_ville' => 'Marrakech',   'code_postal' => '40000'],
            ['nom_ville' => 'Fès',         'code_postal' => '30000'],
            ['nom_ville' => 'Tanger',      'code_postal' => '90000'],
            ['nom_ville' => 'Agadir',      'code_postal' => '80000'],
            ['nom_ville' => 'Oujda',       'code_postal' => '60000'],
            ['nom_ville' => 'Kénitra',     'code_postal' => '14000'],
            ['nom_ville' => 'Tétouan',     'code_postal' => '93000'],
            ['nom_ville' => 'Meknès',      'code_postal' => '50000'],
        ];

        DB::table('Villes')->insert($villes);
    }
}

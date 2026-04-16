<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Run order respects foreign key constraints.
     */
    public function run(): void
    {
        $this->call([
            VilleSeeder::class,
            NiveauEtudeSeeder::class,
            CompetenceSeeder::class,
            UtilisateurSeeder::class,
            EliteEnterpriseSeeder::class,
            EliteEnterpriseOfferSeeder::class,
            EtudiantSeeder::class,
            EntrepriseSeeder::class,
            OpportuniteSeeder::class,
            EtudiantCompetenceSeeder::class,
            OpportuniteCompetenceSeeder::class,
            TechnicalStackSeeder::class,
            ComprehensiveTestDataSeeder::class,
        ]);

        // User::factory(10)->create();
    }
}

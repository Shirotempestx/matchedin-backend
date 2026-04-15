<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntrepriseSeeder extends Seeder
{
    /**
     * Seed the Entreprises table with company profiles.
     */
    public function run(): void
    {
        // Fetch company user IDs
        $entreprises_users = DB::table('Utilisateurs')
            ->where('role', 'Entreprise')
            ->orderBy('email')
            ->pluck('id_utilisateur')
            ->toArray();

        $villes = DB::table('Villes')->pluck('id_ville', 'nom_ville');

        $entreprises = [
            [
                'id_entreprise'  => Str::uuid()->toString(),
                'id_utilisateur' => $entreprises_users[0], // contact@maroc-digital.ma
                'id_ville'       => $villes['Casablanca'],
                'nom_entreprise' => 'Maroc Digital',
                'secteur'        => 'Technologies de l\'information',
                'description'    => 'Agence digitale spécialisée dans le développement web, mobile et les solutions cloud pour les entreprises marocaines.',
            ],
            [
                'id_entreprise'  => Str::uuid()->toString(),
                'id_utilisateur' => $entreprises_users[1], // recrutement@atlas-solutions.ma
                'id_ville'       => $villes['Rabat'],
                'nom_entreprise' => 'Atlas Solutions',
                'secteur'        => 'Conseil en ingénierie',
                'description'    => 'Cabinet de conseil en transformation digitale et ingénierie logicielle, accompagnant les grandes entreprises dans leur modernisation.',
            ],
            [
                'id_entreprise'  => Str::uuid()->toString(),
                'id_utilisateur' => $entreprises_users[2], // rh@technovate.ma
                'id_ville'       => $villes['Tanger'],
                'nom_entreprise' => 'TechnoVate',
                'secteur'        => 'Fintech',
                'description'    => 'Startup fintech innovante développant des solutions de paiement mobile et de gestion financière pour le marché africain.',
            ],
        ];

        DB::table('Entreprises')->insert($entreprises);
    }
}

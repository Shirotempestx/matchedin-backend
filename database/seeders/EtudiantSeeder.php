<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EtudiantSeeder extends Seeder
{
    /**
     * Seed the Etudiants table with student profiles.
     */
    public function run(): void
    {
        // Fetch student user IDs
        $etudiants_users = DB::table('Utilisateurs')
            ->where('role', 'Etudiant')
            ->orderBy('email')
            ->pluck('id_utilisateur')
            ->toArray();

        // Fetch ville and niveau IDs
        $villes = DB::table('Villes')->pluck('id_ville', 'nom_ville');
        $niveaux = DB::table('Niveaux_Etude')->pluck('id_niveau', 'libelle');

        $students = [
            [
                'id_etudiant'    => Str::uuid()->toString(),
                'id_utilisateur' => $etudiants_users[0], // ahmed.bennani
                'id_ville'       => $villes['Casablanca'],
                'id_niveau'      => $niveaux['Bac+5 (Master)'],
                'nom'            => 'Bennani',
                'prenom'         => 'Ahmed',
                'bio'            => 'Étudiant en ingénierie logicielle passionné par le développement web full-stack et les technologies cloud.',
            ],
            [
                'id_etudiant'    => Str::uuid()->toString(),
                'id_utilisateur' => $etudiants_users[1], // fatima.elmoussaoui
                'id_ville'       => $villes['Rabat'],
                'id_niveau'      => $niveaux['Bac+3 (Licence)'],
                'nom'            => 'El Moussaoui',
                'prenom'         => 'Fatima',
                'bio'            => 'Étudiante en informatique avec un intérêt particulier pour le design UX/UI et le front-end.',
            ],
            [
                'id_etudiant'    => Str::uuid()->toString(),
                'id_utilisateur' => $etudiants_users[2], // omar.tazi
                'id_ville'       => $villes['Marrakech'],
                'id_niveau'      => $niveaux['Bac+5 (Master)'],
                'nom'            => 'Tazi',
                'prenom'         => 'Omar',
                'bio'            => 'Passionné par la data science et l\'intelligence artificielle. En recherche de stage de fin d\'études.',
            ],
            [
                'id_etudiant'    => Str::uuid()->toString(),
                'id_utilisateur' => $etudiants_users[3], // sara.idrissi
                'id_ville'       => $villes['Fès'],
                'id_niveau'      => $niveaux['Bac+2'],
                'nom'            => 'Idrissi',
                'prenom'         => 'Sara',
                'bio'            => 'Étudiante en développement mobile, compétente en React Native et Flutter.',
            ],
            [
                'id_etudiant'    => Str::uuid()->toString(),
                'id_utilisateur' => $etudiants_users[4], // youssef.amrani
                'id_ville'       => $villes['Tanger'],
                'id_niveau'      => $niveaux['Bac+3 (Licence)'],
                'nom'            => 'Amrani',
                'prenom'         => 'Youssef',
                'bio'            => 'Développeur back-end avec une solide expérience en PHP/Laravel et bases de données relationnelles.',
            ],
        ];

        DB::table('Etudiants')->insert($students);
    }
}

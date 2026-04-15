<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UtilisateurSeeder extends Seeder
{
    /**
     * Seed the Utilisateurs table with admin, students, and companies.
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        $utilisateurs = [
            // Admin
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'admin@matchendin.ma',
                'mot_de_passe'   => $password,
                'role'           => 'Admin',
                'created_at'     => now(),
            ],
            // Etudiants
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'ahmed.bennani@email.com',
                'mot_de_passe'   => $password,
                'role'           => 'Etudiant',
                'created_at'     => now(),
            ],
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'fatima.elmoussaoui@email.com',
                'mot_de_passe'   => $password,
                'role'           => 'Etudiant',
                'created_at'     => now(),
            ],
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'youssef.amrani@email.com',
                'mot_de_passe'   => $password,
                'role'           => 'Etudiant',
                'created_at'     => now(),
            ],
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'sara.idrissi@email.com',
                'mot_de_passe'   => $password,
                'role'           => 'Etudiant',
                'created_at'     => now(),
            ],
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'omar.tazi@email.com',
                'mot_de_passe'   => $password,
                'role'           => 'Etudiant',
                'created_at'     => now(),
            ],
            // Entreprises
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'contact@maroc-digital.ma',
                'mot_de_passe'   => $password,
                'role'           => 'Entreprise',
                'created_at'     => now(),
            ],
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'rh@technovate.ma',
                'mot_de_passe'   => $password,
                'role'           => 'Entreprise',
                'created_at'     => now(),
            ],
            [
                'id_utilisateur' => Str::uuid()->toString(),
                'email'          => 'recrutement@atlas-solutions.ma',
                'mot_de_passe'   => $password,
                'role'           => 'Entreprise',
                'created_at'     => now(),
            ],
        ];

        DB::table('Utilisateurs')->insert($utilisateurs);
    }
}

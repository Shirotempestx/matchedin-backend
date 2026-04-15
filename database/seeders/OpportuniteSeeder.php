<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpportuniteSeeder extends Seeder
{
    /**
     * Seed the Opportunites table with job/internship/hackathon offers.
     */
    public function run(): void
    {
        $entreprises = DB::table('Entreprises')->pluck('id_entreprise', 'nom_entreprise');
        $villes = DB::table('Villes')->pluck('id_ville', 'nom_ville');
        $niveaux = DB::table('Niveaux_Etude')->pluck('id_niveau', 'libelle');

        $opportunites = [
            [
                'id_opportunite'  => Str::uuid()->toString(),
                'id_entreprise'   => $entreprises['Maroc Digital'],
                'id_ville'        => $villes['Casablanca'],
                'id_niveau_requis'=> $niveaux['Bac+3 (Licence)'],
                'titre'           => 'Stage Développeur Full-Stack Laravel/React',
                'description'     => 'Rejoignez notre équipe de développement pour un stage de 6 mois. Vous travaillerez sur des projets web innovants utilisant Laravel et React.',
                'type'            => 'Stage',
                'statut'          => 'Active',
                'date_publication'=> '2026-03-15',
            ],
            [
                'id_opportunite'  => Str::uuid()->toString(),
                'id_entreprise'   => $entreprises['Maroc Digital'],
                'id_ville'        => $villes['Casablanca'],
                'id_niveau_requis'=> $niveaux['Bac+5 (Master)'],
                'titre'           => 'Ingénieur DevOps',
                'description'     => 'Poste CDI pour un ingénieur DevOps expérimenté. Gestion des infrastructures cloud, CI/CD, et conteneurisation avec Docker/Kubernetes.',
                'type'            => 'Emploi',
                'statut'          => 'Active',
                'date_publication'=> '2026-03-10',
            ],
            [
                'id_opportunite'  => Str::uuid()->toString(),
                'id_entreprise'   => $entreprises['Atlas Solutions'],
                'id_ville'        => $villes['Rabat'],
                'id_niveau_requis'=> $niveaux['Bac+5 (Master)'],
                'titre'           => 'Consultant Data Analyst',
                'description'     => 'Intégrez notre équipe data pour accompagner nos clients dans l\'analyse et la valorisation de leurs données. SQL, Python et Power BI requis.',
                'type'            => 'Emploi',
                'statut'          => 'Active',
                'date_publication'=> '2026-03-12',
            ],
            [
                'id_opportunite'  => Str::uuid()->toString(),
                'id_entreprise'   => $entreprises['Atlas Solutions'],
                'id_ville'        => $villes['Rabat'],
                'id_niveau_requis'=> $niveaux['Bac+2'],
                'titre'           => 'Stage Assistant Chef de Projet IT',
                'description'     => 'Stage de 4 mois au sein de l\'équipe PMO. Participation à la gestion de projets digitaux pour des clients grands comptes.',
                'type'            => 'Stage',
                'statut'          => 'Active',
                'date_publication'=> '2026-03-20',
            ],
            [
                'id_opportunite'  => Str::uuid()->toString(),
                'id_entreprise'   => $entreprises['TechnoVate'],
                'id_ville'        => $villes['Tanger'],
                'id_niveau_requis'=> $niveaux['Bac+3 (Licence)'],
                'titre'           => 'Développeur Mobile React Native',
                'description'     => 'Développement de notre application mobile de paiement. Expérience en React Native et intégration d\'APIs REST souhaitée.',
                'type'            => 'Emploi',
                'statut'          => 'Active',
                'date_publication'=> '2026-03-18',
            ],
            [
                'id_opportunite'  => Str::uuid()->toString(),
                'id_entreprise'   => $entreprises['TechnoVate'],
                'id_ville'        => $villes['Tanger'],
                'id_niveau_requis'=> $niveaux['Bac'],
                'titre'           => 'Hackathon FinTech Challenge 2026',
                'description'     => 'Participez à notre hackathon de 48h ! Développez des solutions innovantes pour l\'inclusion financière au Maroc. Prix : 50 000 MAD.',
                'type'            => 'Hackathon',
                'statut'          => 'Active',
                'date_publication'=> '2026-03-25',
            ],
        ];

        DB::table('Opportunites')->insert($opportunites);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class EliteEnterpriseSeeder extends Seeder
{
    /**
     * Seed a test enterprise account with the elite subscription tier.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'elite@matchendin.ma'],
            [
                'name' => 'Elite MatchendIN',
                'password' => 'Elite123!',
                'role' => 'enterprise',
                'status' => 'active',
                'subscription_tier' => 'elite',
                'company_name' => 'Elite MatchendIN',
                'industry' => 'Technology',
                'company_size' => '51-200',
                'website' => 'https://elite.matchendin.test',
                'preferred_language' => 'fr',
                'description' => 'Compte de test entreprise Elite pour valider les fonctionnalités premium.',
            ]
        );
    }
}
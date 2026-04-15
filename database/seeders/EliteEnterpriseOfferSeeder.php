<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EliteEnterpriseOfferSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $eliteEnterprise = User::query()->where('email', 'elite@matchendin.ma')->first();

        if (!$eliteEnterprise) {
            return;
        }

        DB::table('offres')->updateOrInsert(
            ['id' => 4],
            [
                'user_id' => $eliteEnterprise->id,
                'title' => 'Full-Stack Developer',
                'description' => "We are looking for a Full-Stack Developer to join our Elite enterprise test account.\n\nThis offer exists so the edit page can be validated end to end.",
                'location' => 'Casablanca',
                'work_mode' => 'Remote',
                'salary_min' => null,
                'salary_max' => null,
                'contract_type' => 'CDI',
                'skills_required' => json_encode([
                    ['id' => 1, 'level' => 3],
                    ['id' => 2, 'level' => 3],
                    ['id' => 3, 'level' => 3],
                ]),
                'is_active' => true,
                'validation_status' => 'pending',
                'views_count' => 0,
                'start_date' => Carbon::parse('2026-04-13 00:00:00'),
                'end_date' => Carbon::parse('2026-04-20 00:00:00'),
                'internship_period' => null,
                'niveau_etude' => 'Bac+2',
                'places_demanded' => 6,
                'created_at' => Carbon::parse('2026-04-15 09:31:34'),
                'updated_at' => Carbon::now(),
            ]
        );
    }
}
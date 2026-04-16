<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Skill;

class Offre extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'work_mode',
        'salary_min',
        'salary_max',
        'contract_type',
        'skills_required',
        'is_active',
        'validation_status',
        'views_count',
        'start_date',
        'end_date',
        'internship_period',
        'niveau_etude',
        'places_demanded',
    ];

    protected $appends = ['skills_details'];

    protected function casts(): array
    {
        return [
            'skills_required' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postulations(): HasMany
    {
        return $this->hasMany(Postulation::class, 'offre_id');
    }

    /**
     * Resolve skill IDs + levels into full objects.
     */
    public function getSkillsDetailsAttribute()
    {
        $skillsRequired = $this->skills_required;
        if (!$skillsRequired || !is_array($skillsRequired)) {
            return [];
        }

        $ids = array_column($skillsRequired, 'id');
        $skills = Skill::whereIn('id_competence', $ids)->get()->keyBy('id_competence');

        return collect($skillsRequired)->map(function ($item) use ($skills) {
            $skill = $skills->get($item['id']);
            return [
                'id' => $item['id'],
                'level' => $item['level'],
                'name' => $skill ? $skill->nom_competence : 'Unknown',
                'category' => $skill ? $skill->category : 'General'
            ];
        });
    }

    /**
     * Calculate the match percentage for a given user against this offer's required skills.
     */
    public function calculateMatchPercentage(?User $user): int
    {
        if (!$user) return 0;

        $offerSkills = $this->skills_required;
        if (empty($offerSkills) || !is_array($offerSkills)) {
            return 100; // If no skills required, it's a 100% match by default
        }

        $userSkills = $user->skill_ids;
        if (empty($userSkills) || !is_array($userSkills)) {
            return 0; // If student has no skills but offer requires some
        }

        // Map user skills for O(1) lookup
        $userSkillsMap = [];
        foreach ($userSkills as $skill) {
            if (is_array($skill) && isset($skill['id'])) {
                $userSkillsMap[(int) $skill['id']] = (int) ($skill['level'] ?? 1);
                continue;
            }

            if (is_numeric($skill)) {
                $userSkillsMap[(int) $skill] = 1;
            }
        }

        $totalRequiredWeight = 0;
        $studentScore = 0;

        foreach ($offerSkills as $reqSkill) {
            if (!is_array($reqSkill) || !isset($reqSkill['id'])) {
                continue;
            }

            $reqId = (int) $reqSkill['id'];
            $reqLevel = (int) ($reqSkill['level'] ?? 1);

            $totalRequiredWeight += $reqLevel;

            if (isset($userSkillsMap[$reqId])) {
                $studentLevel = (int) $userSkillsMap[$reqId];
                // Student gets points up to the required level
                $studentScore += min($studentLevel, $reqLevel);
            }
        }

        if ($totalRequiredWeight === 0) return 100;

        return (int) round(($studentScore / $totalRequiredWeight) * 100);
    }
}

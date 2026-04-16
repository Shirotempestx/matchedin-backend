<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $student = $request->user();

        if (!$student || !$this->isStudentRole($student->role ?? null)) {
            return response()->json([
                'message' => __('messages.student_profile_not_found'),
            ], 404);
        }

        return response()->json([
            'data' => $this->buildProfilePayload($student, true),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $student = $request->user();

        if (!$student || !$this->isStudentRole($student->role ?? null)) {
            return response()->json([
                'message' => 'Student profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:220'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'city' => ['nullable', 'string', 'max:100'],
            'headline' => ['nullable', 'string', 'max:150'],
            'availability' => ['nullable', 'string', 'max:120'],
            'workMode' => ['nullable', 'string', 'max:60'],
            'githubUrl' => ['nullable', 'string', 'max:255'],
            'linkedinUrl' => ['nullable', 'string', 'max:255'],
            'portfolioUrl' => ['nullable', 'string', 'max:255'],
            'cvUrl' => ['nullable', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'banner_url' => ['nullable', 'string', 'max:500'],
            'avatarUrl' => ['nullable', 'string', 'max:500'],
            'bannerUrl' => ['nullable', 'string', 'max:500'],
            'profile_type' => ['nullable', 'string', 'in:IT,NON_IT'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'preferred_language' => ['nullable', 'in:fr,en'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['nullable'],
        ]);

        if (array_key_exists('name', $validated)) $student->name = $validated['name'];
        if (array_key_exists('bio', $validated)) $student->bio = $validated['bio'];
        if (array_key_exists('headline', $validated)) $student->title = $validated['headline'];
        if (array_key_exists('workMode', $validated)) $student->work_mode = $validated['workMode'];
        if (array_key_exists('availability', $validated)) $student->availability = $validated['availability'];
        if (array_key_exists('city', $validated)) $student->country = $validated['city'];
        if (array_key_exists('cvUrl', $validated)) $student->cv_url = $validated['cvUrl'];
        if (array_key_exists('githubUrl', $validated)) $student->website = $validated['githubUrl'];
        if (array_key_exists('linkedinUrl', $validated)) $student->linkedin_url = $validated['linkedinUrl'];
        if (array_key_exists('portfolioUrl', $validated)) $student->portfolio_url = $validated['portfolioUrl'];
        if (array_key_exists('avatar_url', $validated)) $student->avatar_url = $validated['avatar_url'];
        if (array_key_exists('banner_url', $validated)) $student->banner_url = $validated['banner_url'];
        if (array_key_exists('avatarUrl', $validated)) $student->avatar_url = $validated['avatarUrl'];
        if (array_key_exists('bannerUrl', $validated)) $student->banner_url = $validated['bannerUrl'];
        if (array_key_exists('profile_type', $validated)) $student->profile_type = $validated['profile_type'];
        if (array_key_exists('education_level', $validated)) $student->education_level = $validated['education_level'];
        if (array_key_exists('preferred_language', $validated)) $student->preferred_language = $validated['preferred_language'];
        if (array_key_exists('skills', $validated)) $student->skill_ids = $validated['skills'];

        $student->save();

        return response()->json([
            'message' => __('messages.profile_updated_successfully'),
            'data' => $this->buildProfilePayload($student, true),
        ]);
    }

    public function showPublic(string $slug): JsonResponse
    {
        $nameFromSlug = str_replace('-', ' ', $slug);

        $student = User::where('role', 'student')
            ->whereRaw('lower(name) = ?', [strtolower($nameFromSlug)])
            ->first();

        if (!$student) {
            return response()->json([
                'message' => __('messages.public_student_profile_not_found'),
            ], 404);
        }

        return response()->json([
            'data' => $this->buildProfilePayload($student, false),
        ]);
    }

    private function buildProfilePayload(User $student, bool $isOwner): array
    {
        $rawSkills = $student->skill_ids ?? [];
        $skills = [];

        if (is_array($rawSkills) && count($rawSkills) > 0) {
            $skillIds = collect($rawSkills)->pluck('id')->filter()->toArray();
            $fetchedSkills = Skill::whereIn('id_competence', $skillIds)->get()->keyBy('id_competence');

            foreach ($rawSkills as $rawSkill) {
                if (isset($rawSkill['id']) && $fetchedSkills->has($rawSkill['id'])) {
                    $skillModel = $fetchedSkills->get($rawSkill['id']);
                    $skills[] = [
                        'id' => $skillModel->id_competence,
                        'name' => $skillModel->nom_competence,
                        'category' => $skillModel->category ?? 'IT',
                        'level' => $rawSkill['level'] ?? 3,
                    ];
                } elseif (is_string($rawSkill)) {
                    $skills[] = [
                        'id' => 0,
                        'name' => $rawSkill,
                        'category' => 'IT',
                        'level' => 3,
                    ];
                }
            }
        }

        $completion = min(
            100,
            30
            + ($student->bio ? 25 : 0)
            + ($student->country ? 15 : 0)
            + ($student->education_level ? 15 : 0)
            + (count($skills) > 0 ? 15 : 0)
        );

        $payload = [
            'id' => $student->id,
            'slug' => Str::slug($student->name),
            'name' => $student->name,
            'avatarUrl' => $this->normalizeMediaUrl($student->avatar_url ?? null),
            'bannerUrl' => $this->normalizeMediaUrl($student->banner_url ?? null),
            'headline' => $student->title ?: '',
            'city' => $student->country ?: '',
            'bio' => $student->bio ?: '',
            'completion' => $completion,
            'availability' => $student->availability ?: '',
            'workMode' => $student->work_mode ?: '',
            'education_level' => $student->education_level ?: '',
            'profile_type' => $student->profile_type,
            'links' => array_values(array_filter([
                $student->website,
                $student->linkedin_url,
            ])),
            'skills' => $skills,
            'projects' => [],
        ];

        if ($isOwner) {
            $payload['email'] = $student->email;
            $payload['githubUrl'] = $student->website ?? '';
            $payload['linkedinUrl'] = $student->linkedin_url ?? '';
            $payload['portfolioUrl'] = $student->portfolio_url ?? '';
            $payload['avatarUrl'] = $this->normalizeMediaUrl($student->avatar_url ?? null);
            $payload['cvUrl'] = $student->cv_url ?? '';
            $payload['privateStats'] = [
                'applications' => 0,
                'favorites' => 0,
                'strongMatches' => 0,
            ];
            $payload['preferred_language'] = $student->preferred_language ?? 'fr';
        }

        return $payload;
    }

    private function normalizeMediaUrl(?string $url): string
    {
        if (!is_string($url) || trim($url) === '') {
            return '';
        }

        $trimmed = trim($url);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            if (str_contains($trimmed, '/api/uploads/')) {
                return $trimmed;
            }

            $storageNeedle = '/storage/uploads/';
            $storagePos = strpos($trimmed, $storageNeedle);
            if ($storagePos !== false) {
                $relativeFromStorage = substr($trimmed, $storagePos + strlen('/storage/'));
                return url('/api/uploads/' . ltrim($relativeFromStorage, '/'));
            }

            $uploadsNeedle = '/uploads/';
            $uploadsPos = strpos($trimmed, $uploadsNeedle);
            if ($uploadsPos !== false) {
                $relativeFromUploads = substr($trimmed, $uploadsPos + 1);
                return url('/api/uploads/' . ltrim($relativeFromUploads, '/'));
            }

            return $trimmed;
        }

        if (str_starts_with($trimmed, '/api/uploads/')) {
            return url($trimmed);
        }

        if (str_starts_with($trimmed, '/storage/uploads/')) {
            $relativePath = ltrim(substr($trimmed, strlen('/storage/')), '/');
            return url('/api/uploads/' . $relativePath);
        }

        if (str_starts_with($trimmed, 'storage/uploads/')) {
            $relativePath = ltrim(substr($trimmed, strlen('storage/')), '/');
            return url('/api/uploads/' . $relativePath);
        }

        if (str_starts_with($trimmed, '/uploads/')) {
            return url('/api/uploads/' . ltrim($trimmed, '/'));
        }

        if (str_starts_with($trimmed, 'uploads/')) {
            return url('/api/uploads/' . $trimmed);
        }

        return url('/api/uploads/' . ltrim($trimmed, '/'));
    }

    private function isStudentRole(mixed $role): bool
    {
        if (!is_string($role)) {
            return false;
        }

        $normalized = strtolower(trim($role));
        return in_array($normalized, ['student', 'etudiant', 'étudiant'], true);
    }
}

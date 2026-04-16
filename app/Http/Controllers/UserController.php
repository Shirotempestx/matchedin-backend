<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * List all students (for recruiters) with advanced searching/filtering.
     */
    public function indexStudents(Request $request)
    {
        $entreprise = $request->user();
        if ($entreprise->role !== 'enterprise') {
            return response()->json(['message' => 'Accès réservé aux entreprises.'], 403);
        }

        $hasCountry = Schema::hasColumn('users', 'country');
        $hasWorkMode = Schema::hasColumn('users', 'work_mode');
        $hasSalaryMin = Schema::hasColumn('users', 'salary_min');
        $hasProfileType = Schema::hasColumn('users', 'profile_type');
        $hasEducationLevel = Schema::hasColumn('users', 'education_level');
        $hasSkillIds = Schema::hasColumn('users', 'skill_ids');

        $select = ['id', 'name', 'email'];
        if ($hasCountry) {
            $select[] = 'country';
        }
        if ($hasWorkMode) {
            $select[] = 'work_mode';
        }
        if ($hasSalaryMin) {
            $select[] = 'salary_min';
        }
        if ($hasProfileType) {
            $select[] = 'profile_type';
        }
        if ($hasEducationLevel) {
            $select[] = 'education_level';
        }
        if ($hasSkillIds) {
            $select[] = 'skill_ids';
        }

        $query = User::where('role', 'student')
            ->select($select);

        // Optional search by text (name, profile_type, or bio if applicable)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search, $hasProfileType) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");

                if ($hasProfileType) {
                    $q->orWhere('profile_type', 'like', "%{$search}%");
                }
            });
        }

        // Optional filter by location
        if ($request->filled('location')) {
            if ($hasCountry) {
                $query->where('country', 'like', '%' . $request->input('location') . '%');
            }
        }

        // Optional filter by education level
        if ($request->filled('education_level')) {
            if ($hasEducationLevel) {
                $eduLevel = str_replace('Bac ', 'Bac+', $request->input('education_level'));
                $query->where('education_level', 'like', '%' . $eduLevel . '%');
            }
        }

        // Optional filter by multiple work_modes
        if ($request->has('work_mode')) {
            if ($hasWorkMode) {
                $workModes = is_array($request->input('work_mode')) ? $request->input('work_mode') : explode(',', $request->input('work_mode'));
                if (!empty($workModes)) {
                    $query->whereIn('work_mode', $workModes);
                }
            }
        }

        // Optional filter by skill_ids
        if ($request->filled('skills')) {
            if ($hasSkillIds) {
                $skills = is_array($request->input('skills')) ? $request->input('skills') : explode(',', $request->input('skills'));
                if (!empty($skills)) {
                    $query->where(function($q) use ($skills) {
                        foreach ($skills as $skillId) {
                            $q->whereJsonContains('skill_ids', ['id' => (int) $skillId]);
                        }
                    });
                }
            }
        }

        // Optional filter by profile_type
        if ($request->filled('profile_type')) {
            if ($hasProfileType) {
                $val = $request->input('profile_type');
                if (is_array($val)) {
                    $query->whereIn('profile_type', $val);
                } else {
                    if (str_contains($val, ',')) {
                        $query->whereIn('profile_type', explode(',', $val));
                    } else {
                        $query->where('profile_type', '=', $val);
                    }
                }
            }
        }

        // Optional filter by max salary expectations
        if ($request->filled('salary_max')) {
            if ($hasSalaryMin) {
                $query->where(function($q) use ($request) {
                    $q->whereNull('salary_min')
                      ->orWhere('salary_min', '<=', (float) $request->input('salary_max'))
                      ->orWhere('salary_min', '=', '');
                });
            }
        }

        $offres = $entreprise->offres()->where('is_active', true)->get();

        $students = $query->get()->map(function ($student) {
            $student->setAttribute('slug', Str::slug((string) $student->name));
            return $student;
        });

        if ($offres->isNotEmpty()) {
            $students = $students->map(function ($student) use ($offres) {   
                $maxMatch = 0;
                foreach ($offres as $offre) {
                    $match = $offre->calculateMatchPercentage($student);     
                    if ($match > $maxMatch) {
                        $maxMatch = $match;
                    }
                }
                $student->setAttribute('match_percentage', $maxMatch);       
                return $student;
            });

            $students = $students->sortByDesc('match_percentage')->values();
        } else {
            $students = $students->sortBy('name')->values();
        }

        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1; 
        $perPage = 12;
        $items = $students->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(        
            $items,
            $students->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        return response()->json($paginator);
    }
}

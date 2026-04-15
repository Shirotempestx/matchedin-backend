<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    /**
     * Follow or Unfollow an Enterprise (Student Action)
     */
    public function toggleFollowEnterprise(Request $request, $enterpriseId): JsonResponse
    {
        $student = $request->user();

        if ($student->role !== 'student') {
            return response()->json(['message' => 'Only students can follow enterprises.'], 403);
        }

        $enterprise = User::where('id', $enterpriseId)->where('role', 'enterprise')->first();

        if (!$enterprise) {
            return response()->json(['message' => 'Enterprise not found.'], 404);
        }

        $isFollowing = $student->followingEnterprises()->where('enterprise_id', $enterpriseId)->exists();

        if ($isFollowing) {
            $student->followingEnterprises()->detach($enterpriseId);
            return response()->json(['message' => 'Enterprise unfollowed successfully.', 'isFollowing' => false]);
        } else {
            $student->followingEnterprises()->attach($enterpriseId);
            return response()->json(['message' => 'Enterprise followed successfully.', 'isFollowing' => true]);
        }
    }

    /**
     * Check if the current student is following an enterprise
     */
    public function isFollowingEnterprise(Request $request, $enterpriseId): JsonResponse
    {
        $student = $request->user();

        if ($student->role !== 'student') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $isFollowing = $student->followingEnterprises()->where('enterprise_id', $enterpriseId)->exists();

        return response()->json(['isFollowing' => $isFollowing]);
    }

    /**
     * Get all enterprises the student is following
     */
    public function getFollowedEnterprises(Request $request): JsonResponse
    {
        $student = $request->user();

        if ($student->role !== 'student') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $enterprises = $student->followingEnterprises()->withCount('offres')->get();

        return response()->json(['enterprises' => $enterprises]);
    }

    /**
     * Save or Unsave a Student (Enterprise Action)
     */
    public function toggleSaveStudent(Request $request, $studentId): JsonResponse
    {
        $enterprise = $request->user();

        if ($enterprise->role !== 'enterprise' && $enterprise->role !== 'Entreprise') { // account for casing
            return response()->json(['message' => 'Only enterprises can save students.'], 403);
        }

        $student = User::where('id', $studentId)->where('role', 'student')->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $isSaved = $enterprise->savedStudents()->where('student_id', $studentId)->exists();

        if ($isSaved) {
            $enterprise->savedStudents()->detach($studentId);
            return response()->json(['message' => 'Student unsaved successfully.', 'isSaved' => false]);
        } else {
            $enterprise->savedStudents()->attach($studentId);
            return response()->json(['message' => 'Student saved successfully.', 'isSaved' => true]);
        }
    }

    /**
     * Check if the current enterprise saved a student
     */
    public function isStudentSaved(Request $request, $studentId): JsonResponse
    {
        $enterprise = $request->user();

        if ($enterprise->role !== 'enterprise' && $enterprise->role !== 'Entreprise') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $isSaved = $enterprise->savedStudents()->where('student_id', $studentId)->exists();

        return response()->json(['isSaved' => $isSaved]);
    }
}

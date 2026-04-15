<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reported_id' => ['required', 'string', 'max:191'],
            'reportable_type' => ['required', 'in:User,Offre'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_id' => $validated['reported_id'],
            'reportable_type' => $validated['reportable_type'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $admins = User::query()->whereIn('role', ['Admin', 'admin'])->get(['id']);
        foreach ($admins as $admin) {
            InAppNotification::create([
                'user_id' => $admin->id,
                'type' => 'report_created',
                'title' => 'Nouveau signalement',
                'body' => 'Un nouveau signalement est en attente de traitement.',
                'action_url' => '/admin/reports',
                'severity' => 'critical',
                'data' => [
                    'report_id' => $report->id,
                    'reportable_type' => $report->reportable_type,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Report submitted successfully.',
            'report' => $report,
        ], 201);
    }
}

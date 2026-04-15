<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Report::with('reporter:id,name');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(reason) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('reporter', function ($uq) use ($search) {
                      $uq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                  });
            });
        }

        $reports = $query->orderByDesc('created_at')->paginate(10);

        return response()->json($reports);
    }

    public function update(Request $request, Report $report)
    {
        $request->validate([
            'status' => 'required|in:resolved,dismissed'
        ]);

        $report->update(['status' => $request->status]);

        return response()->json(['message' => "Report status updated to {$report->status}", 'report' => $report]);
    }
}

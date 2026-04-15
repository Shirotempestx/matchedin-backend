<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index(Request $request)
    {
        $query = User::whereNotIn('role', ['Admin', 'admin']);

        // Search by name, email, or company name
        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(company_name) LIKE ?', ["%{$search}%"]);
            });
        }

        // Filter by role
        if ($request->has('role') && !empty($request->role) && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle export parameter
        if ($request->has('export') && $request->export === 'true') {
            $users = $query->orderByDesc('created_at')->get();
            return response()->json($users);
        }

        $users = $query->orderByDesc('created_at')->paginate(10);

        return response()->json($users);
    }

    public function suspend(Request $request, User $user)
    {
        if (strtolower($user->role) === 'admin') {
            return response()->json(['message' => 'Cannot suspend an Admin'], 403);
        }

        $request->validate([
            'status' => 'required|in:active,suspended,pending,rejected'
        ]);

        $user->update(['status' => $request->status]);

        // Send notification email only for certain status transitions
        if (in_array($request->status, ['active', 'rejected'])) {
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)
                    ->locale($user->preferred_language ?? 'fr')
                    ->send(new \App\Mail\EnterpriseAccountStatusChanged($user, $request->status));
            } catch (\Exception $e) {
                \Log::error("Failed to send status changed email to {$user->email}: " . $e->getMessage());
            }
        }

        return response()->json(['message' => "User status updated to {$user->status}", 'user' => $user]);
    }

    public function destroy(User $user)
    {
        if (strtolower($user->role) === 'admin') {
            return response()->json(['message' => 'Cannot delete an Admin'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}

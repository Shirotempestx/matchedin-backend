<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeUser;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            // Add other fields as optional or required based on role
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'student',
            'status' => ($request->role ?? 'student') === 'enterprise' ? 'pending' : 'active',
            'country' => $request->country,
            'work_mode' => $request->work_mode,
            'salary_min' => $request->salary_min,
            'profile_type' => $request->profile_type,
            'skill_ids' => $request->skill_ids,
            'company_name' => $request->company_name,
            'industry' => $request->industry,
            'company_size' => $request->company_size,
            'website' => $request->website,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Send Welcome Email
        try {
            Mail::to($user->email)->send(new WelcomeUser($user));
        } catch (\Exception $e) {
            \Log::error("Failed to send welcome email to {$user->email}: " . $e->getMessage());
        }

        return response()->json(['message' => 'Registered successfully', 'user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['message' => 'Logged in successfully', 'user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}

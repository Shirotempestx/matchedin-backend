<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->file('file')->isValid()) {
            $path = $request->file('file')->store('uploads', 'public');
            
            return response()->json([
                'url' => url('/api/uploads/' . $path),
            ]);
        }

        return response()->json([
            'message' => 'Upload failed.',
        ], 400);
    }

    public function show(Request $request, string $path)
    {
        $normalizedPath = trim($path, '/');

        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            abort(404);
        }

        if (!str_starts_with($normalizedPath, 'uploads/')) {
            $normalizedPath = 'uploads/' . $normalizedPath;
        }

        if (!Storage::disk('public')->exists($normalizedPath)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('public')->path($normalizedPath),
            [
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]
        );
    }
}

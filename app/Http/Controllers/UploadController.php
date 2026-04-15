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
                'url' => asset('storage/' . $path),
            ]);
        }

        return response()->json([
            'message' => 'Upload failed.',
        ], 400);
    }
}

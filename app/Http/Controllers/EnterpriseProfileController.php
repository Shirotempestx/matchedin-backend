<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnterpriseProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $enterprise = $request->user();

        if (!$enterprise || $enterprise->role !== 'enterprise') {
            return response()->json([
                'message' => __('messages.enterprise_profile_not_found'),
            ], 404);
        }

        return response()->json([
            'data' => $this->buildProfilePayload($enterprise, true),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $enterprise = $request->user();

        if (!$enterprise || $enterprise->role !== 'enterprise') {
            return response()->json([
                'message' => 'Enterprise profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:4000'],
            'industry' => ['nullable', 'string', 'max:150'],
            'company_size' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'banner_url' => ['nullable', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', 'in:fr,en'],
        ]);

        if (array_key_exists('company_name', $validated)) $enterprise->company_name = $validated['company_name'];
        if (array_key_exists('description', $validated)) $enterprise->description = $validated['description'];
        if (array_key_exists('industry', $validated)) $enterprise->industry = $validated['industry'];
        if (array_key_exists('company_size', $validated)) $enterprise->company_size = $validated['company_size'];
        if (array_key_exists('country', $validated)) $enterprise->country = $validated['country'];
        if (array_key_exists('website', $validated)) $enterprise->website = $validated['website'];
        if (array_key_exists('logo_url', $validated)) $enterprise->logo_url = $validated['logo_url'];
        if (array_key_exists('banner_url', $validated)) $enterprise->banner_url = $validated['banner_url'];
        if (array_key_exists('contact_phone', $validated)) $enterprise->contact_phone = $validated['contact_phone'];
        if (array_key_exists('preferred_language', $validated)) $enterprise->preferred_language = $validated['preferred_language'];

        $enterprise->save();

        return response()->json([
            'message' => __('messages.enterprise_profile_updated_successfully'),
            'data' => $this->buildProfilePayload($enterprise, true),
        ]);
    }

    public function showPublic(string $slug): JsonResponse
    {
        $nameFromSlug = str_replace('-', ' ', $slug);

        $enterprise = User::where('role', 'enterprise')
            ->whereRaw('lower(company_name) = ?', [strtolower($nameFromSlug)])
            ->first();

        if (!$enterprise) {
            return response()->json([
                'message' => __('messages.public_enterprise_profile_not_found'),
            ], 404);
        }

        return response()->json([
            'data' => $this->buildProfilePayload($enterprise, false),
        ]);
    }

    private function buildProfilePayload(User $enterprise, bool $isOwner): array
    {
        $completion = min(
            100,
            30
            + ($enterprise->description ? 25 : 0)
            + ($enterprise->country ? 15 : 0)
            + ($enterprise->industry ? 15 : 0)
            + ($enterprise->logo_url ? 15 : 0)
        );

        $payload = [
            'id' => $enterprise->id,
            'slug' => Str::slug($enterprise->company_name ?? $enterprise->name),
            'name' => $enterprise->company_name ?: $enterprise->name ?: '',
            'industry' => $enterprise->industry ?: '',
            'location' => $enterprise->country ?: '',
            'description' => $enterprise->description ?: '',
            'completion' => $completion,
            'company_size' => $enterprise->company_size ?: '',
            'website' => $enterprise->website ?? '',
            'logo_url' => $enterprise->logo_url ?? '',
            'banner_url' => $enterprise->banner_url ?? '',
            'followers_count' => $enterprise->followers()->count(),
        ];

        if ($isOwner) {
            $payload['email'] = $enterprise->email;
            $payload['contact_phone'] = $enterprise->contact_phone ?? '';
            $payload['privateStats'] = [
                'activeOffres' => $enterprise->offres()->count(),
                'totalCandidates' => 0,
            ];
            $payload['preferred_language'] = $enterprise->preferred_language ?? 'fr';
        }

        return $payload;
    }
}

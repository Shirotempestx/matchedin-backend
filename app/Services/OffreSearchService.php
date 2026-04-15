<?php

namespace App\Services;

use App\Models\Offre;
use Illuminate\Database\Eloquent\Builder;

class OffreSearchService
{
    public function search(array $filters)
    {
        $query = Offre::with(['skills', 'company']); // Assuming company relationship exists

        // TEXT SEARCH (q)
        if (!empty($filters['q'])) {
            $q = '%' . strtolower($filters['q']) . '%';
            $query->where(function (Builder $builder) use ($q) {
                $builder->whereRaw('LOWER(title) LIKE ?', [$q])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$q]);
            });
        }

        if (!empty($filters['type']) && is_array($filters['type'])) {
            $query->whereIn('contract_type', $filters['type']);
        }

        if (!empty($filters['location'])) {
            $query->whereRaw('LOWER(location) LIKE ?', ['%' . strtolower($filters['location']) . '%']);
        }

        if (isset($filters['remote'])) {
            $query->where('work_mode', filter_var($filters['remote'], FILTER_VALIDATE_BOOLEAN) ? 'remote' : 'onsite');
        }

        if (!empty($filters['skills']) && is_array($filters['skills'])) {
            $mode = $filters['skills_match_mode'] ?? 'any';
            if ($mode === 'all') {
                foreach ($filters['skills'] as $skillId) {
                    $query->whereHas('skills', fn($q) => $q->where('skills.id', $skillId));
                }
            } else {
                $query->whereHas('skills', fn($q) => $q->whereIn('skills.id', $filters['skills']));
            }
        }

        if (!empty($filters['posted_within'])) {
            $query->where('created_at', '>=', now()->subDays((int)$filters['posted_within']));
        }

        $aggregations = [
            'types' => (clone $query)->selectRaw('contract_type, COUNT(*) as count')->groupBy('contract_type')->pluck('count', 'contract_type'),
            'locations' => (clone $query)->select('location')->distinct()->pluck('location'),
        ];

        $sortBy = $filters['sort_by'] ?? 'date_posted';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        if ($sortBy === 'popularity') {
            $query->withCount('favorites')->orderBy('favorites_count', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        $perPage = min((int)($filters['per_page'] ?? 10), 50);
        $paginator = $query->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'filters_applied' => $filters,
                'sort' => ['by' => $sortBy, 'order' => $sortOrder]
            ],
            'aggregations' => $aggregations
        ];
    }
}

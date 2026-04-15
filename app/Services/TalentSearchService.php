<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TalentSearchService
{
    public function search(array $filters)
    {
        // Assuming role 'student' represents talent
        $query = User::with(['skills'])->where('role', 'student');

        if (!empty($filters['q'])) {
            $q = '%' . strtolower($filters['q']) . '%';
            $query->where(function (Builder $builder) use ($q) {
                // Adjust if bio and name don't match exactly your DB. They are safe default fields.
                $builder->whereRaw('LOWER(name) LIKE ?', [$q]);
                 // If Users table has bio, we'd uncomment: ->orWhereRaw('LOWER(bio) LIKE ?', [$q]);
            });
        }

        if (!empty($filters['skills']) && is_array($filters['skills'])) {
            $query->whereHas('skills', fn($q) => $q->whereIn('skills.id', $filters['skills']));
        }

        if (!empty($filters['filiere'])) {
            $query->where('filiere', 'like', '%' . $filters['filiere'] . '%'); 
        }

        if (!empty($filters['location'])) {
            $query->where('location', 'like', '%' . $filters['location'] . '%');
        }

        $aggregations = [
            'locations' => (clone $query)->select('location')->distinct()->pluck('location'),
        ];

        $sortBy = $filters['sort_by'] ?? 'score';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query->orderBy('created_at', $sortOrder);

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

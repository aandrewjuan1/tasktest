<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FiltersItems
{
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['priority']) && $filters['priority'] && $filters['priority'] !== 'all') {
            $query->filterByPriority($filters['priority']);
        }

        if (isset($filters['status']) && $filters['status'] && $filters['status'] !== 'all') {
            $query->filterByStatus($filters['status']);
        }

        if (isset($filters['tagIds']) && ! empty($filters['tagIds'])) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $filters['tagIds']));
        }

        return $query;
    }

    protected function filterByType(Builder $query, ?string $type): Builder
    {
        // Type filtering is handled at the query level (Task::query() vs Event::query())
        return $query;
    }

    protected function filterByPriority(Builder $query, ?string $priority): Builder
    {
        if ($priority && $priority !== 'all') {
            $query->filterByPriority($priority);
        }

        return $query;
    }

    protected function filterByStatus(Builder $query, ?string $status): Builder
    {
        if ($status && $status !== 'all') {
            $query->filterByStatus($status);
        }

        return $query;
    }

    protected function filterByTags(Builder $query, array $tagIds): Builder
    {
        if (! empty($tagIds)) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        return $query;
    }
}

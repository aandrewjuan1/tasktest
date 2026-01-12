<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait SortsItems
{
    protected function applySorting(Builder $query, ?string $sortBy, string $direction = 'asc'): Builder
    {
        if (! $sortBy) {
            return $query->orderBy('created_at', 'desc');
        }

        return $query->orderByField($sortBy, $direction);
    }

    protected function sortCollection(Collection $items, ?string $sortBy, string $sortDirection = 'asc'): Collection
    {
        if (! $sortBy) {
            return $items->sortBy('created_at', SORT_REGULAR, $sortDirection === 'desc');
        }

        return $items->sort(function ($a, $b) use ($sortBy, $sortDirection) {
            $direction = $sortDirection === 'desc' ? -1 : 1;

            return match ($sortBy) {
                'priority' => $direction * $this->comparePriority($a->priority ?? null, $b->priority ?? null),
                'created_at' => $direction * ($a->created_at <=> $b->created_at),
                'start_datetime' => $direction * ($a->start_datetime <=> $b->start_datetime),
                'end_datetime' => $direction * ($a->end_datetime <=> $b->end_datetime),
                'title' => $direction * strcasecmp($a->title ?? '', $b->title ?? ''),
                'status' => $direction * strcasecmp($a->status?->value ?? '', $b->status?->value ?? ''),
                default => $direction * ($a->created_at <=> $b->created_at),
            };
        })->values();
    }

    protected function comparePriority($priorityA, $priorityB): int
    {
        $priorityOrder = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $valueA = $priorityOrder[$priorityA?->value ?? ''] ?? 0;
        $valueB = $priorityOrder[$priorityB?->value ?? ''] ?? 0;

        return $valueA <=> $valueB;
    }
}

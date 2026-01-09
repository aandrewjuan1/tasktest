@props([
    'filterType' => null,
    'filterPriority' => null,
    'filterStatus' => null,
    'sortBy' => null,
    'sortDirection' => 'asc',
    'hasActiveFilters' => false,
])

@php
    $filterParts = [];

    if ($filterType && $filterType !== 'all') {
        $typeLabel = match ($filterType) {
            'task' => 'tasks',
            'event' => 'events',
            default => $filterType,
        };

        $filterParts[] = "Showing {$typeLabel} only";
    }

    if ($filterPriority && $filterPriority !== 'all') {
        $priorityLabel = ucfirst($filterPriority);
        $filterParts[] = "Priority: {$priorityLabel}";
    }

    if ($filterStatus && $filterStatus !== 'all') {
        $statusLabel = match ($filterStatus) {
            'to_do' => 'To Do',
            'doing' => 'In Progress',
            'done' => 'Done',
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'tentative' => 'Tentative',
            'ongoing' => 'In Progress',
            default => ucfirst($filterStatus),
        };

        $filterParts[] = "Status: {$statusLabel}";
    }

    $filterDescription = implode(' • ', $filterParts);

    $sortDescription = null;

    if ($sortBy) {
        $sortLabel = match ($sortBy) {
            'priority' => 'Priority',
            'created_at' => 'Date Created',
            'start_datetime' => 'Start Date',
            'end_datetime' => 'End Date',
            'title' => 'Title/Name',
            'status' => 'Status',
            default => ucfirst(str_replace('_', ' ', $sortBy)),
        };

        $directionArrow = $sortDirection === 'asc' ? '↑' : '↓';
        $sortDescription = "Sorted by: {$sortLabel} {$directionArrow}";
    }
@endphp

@if($filterDescription || $sortDescription || $hasActiveFilters || $sortBy)
    <div class="flex items-center gap-2 px-3 py-1 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 text-sm text-zinc-600 dark:text-zinc-400">
        @if($filterDescription)
            <span class="font-medium">{{ $filterDescription }}</span>
        @endif

        @if($sortDescription)
            @if($filterDescription)
                <span class="text-zinc-400 dark:text-zinc-500">•</span>
            @endif
            <span class="font-medium">{{ $sortDescription }}</span>
        @endif

        @if($hasActiveFilters || $sortBy)
            <flux:tooltip content="Clear all filters and sorts">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="x-mark"
                    wire:click="$dispatch('clear-all-filters-sorts')"
                    aria-label="Clear all filters and sorts"
                    class="ml-1"
                />
            </flux:tooltip>
        @endif
    </div>
@endif

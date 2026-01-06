<?php

use App\Enums\EventStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component
{
    #[Reactive]
    public Collection $items;

    #[Reactive]
    public ?Carbon $currentDate = null;

    #[Reactive]
    public ?string $filterType = null;

    #[Reactive]
    public ?string $filterPriority = null;

    #[Reactive]
    public ?string $filterStatus = null;

    #[Reactive]
    public ?string $sortBy = null;

    #[Reactive]
    public string $sortDirection = 'asc';

    #[Reactive]
    public bool $hasActiveFilters = false;

    #[Reactive]
    public string $viewMode = 'list';

    public function mount(
        Collection $items,
        ?Carbon $currentDate = null,
        ?string $filterType = null,
        ?string $filterPriority = null,
        ?string $filterStatus = null,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        bool $hasActiveFilters = false,
        string $viewMode = 'list'
    ): void {
        $this->items = $items;
        $this->currentDate = $currentDate ?? now();
        $this->filterType = $filterType;
        $this->filterPriority = $filterPriority;
        $this->filterStatus = $filterStatus;
        $this->sortBy = $sortBy;
        $this->sortDirection = $sortDirection;
        $this->hasActiveFilters = $hasActiveFilters;
        $this->viewMode = $viewMode;
    }

    #[Computed]
    public function sortedItems(): Collection
    {
        // Items are already filtered and sorted by parent component
        return $this->items;
    }

    #[Computed]
    public function filterDescription(): string
    {
        $parts = [];

        if ($this->filterType && $this->filterType !== 'all') {
            $typeLabel = match($this->filterType) {
                'task' => 'tasks',
                'event' => 'events',
                'project' => 'projects',
                default => $this->filterType,
            };
            $parts[] = "Showing {$typeLabel} only";
        }

        if ($this->filterPriority && $this->filterPriority !== 'all') {
            $priorityLabel = ucfirst($this->filterPriority);
            $parts[] = "Priority: {$priorityLabel}";
        }

        if ($this->filterStatus && $this->filterStatus !== 'all') {
            $statusLabel = match($this->filterStatus) {
                'to_do' => 'To Do',
                'doing' => 'In Progress',
                'done' => 'Done',
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'tentative' => 'Tentative',
                default => ucfirst($this->filterStatus),
            };
            $parts[] = "Status: {$statusLabel}";
        }

        return implode(' • ', $parts);
    }

    #[Computed]
    public function sortDescription(): ?string
    {
        if (!$this->sortBy) {
            return null;
        }

        $sortLabel = match($this->sortBy) {
            'priority' => 'Priority',
            'created_at' => 'Date Created',
            'start_datetime' => 'Start Date',
            'end_datetime' => 'End Date',
            'title' => 'Title/Name',
            'status' => 'Status',
            default => ucfirst(str_replace('_', ' ', $this->sortBy)),
        };

        $direction = $this->sortDirection === 'asc' ? '↑' : '↓';

        return "Sorted by: {$sortLabel} {$direction}";
    }
}; ?>

<div
    class="space-y-4"
    x-data="{
        deleted: [],
    }"
    x-on:optimistic-item-deleted.window="
        deleted.push({
            id: $event.detail.itemId,
            type: $event.detail.itemType,
        })
    "
>
    <!-- View Navigation -->
    <x-workspace.view-navigation
        :view-mode="$viewMode"
        :current-date="$currentDate"
        :filter-type="$filterType"
        :filter-priority="$filterPriority"
        :filter-status="$filterStatus"
        :sort-by="$sortBy"
        :sort-direction="$sortDirection"
        :has-active-filters="$hasActiveFilters"
        :filter-description="$this->filterDescription"
        :sort-description="$this->sortDescription"
    />

    <div wire:loading.class="opacity-50" wire:target="goToTodayDate,previousDay,nextDay">
        <div class="space-y-4">
            <!-- Create New Item CTA -->
            <button
                wire:click="$dispatch('open-create-modal')"
                class="w-full bg-white dark:bg-zinc-800 rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600 hover:border-blue-400 dark:hover:border-blue-500 p-8 transition-all hover:bg-zinc-50 dark:hover:bg-zinc-700/50 flex items-center justify-center group cursor-pointer"
            >
                <svg class="w-8 h-8 text-zinc-400 dark:text-zinc-500 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>

            @forelse($this->sortedItems as $item)
                <div
                    wire:key="list-item-{{ $item->item_type }}-{{ $item->id }}"
                    x-show="!deleted.some(d => d.id === {{ $item->id }} && d.type === '{{ $item->item_type }}')"
                >
                    @if($item->item_type === 'task')
                        <x-workspace.task-card :task="$item" />
                    @elseif($item->item_type === 'event')
                        <x-workspace.event-card :event="$item" />
                    @elseif($item->item_type === 'project')
                        <x-workspace.project-card :project="$item" />
                    @endif
                </div>
            @empty
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-12 text-center" aria-live="polite">
            <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">No items found</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Get started by creating a new task, event, or project.
            </p>
        </div>
        @endforelse
        </div>
    </div>
</div>

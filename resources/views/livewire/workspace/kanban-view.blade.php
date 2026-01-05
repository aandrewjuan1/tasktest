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
    public array $itemsByStatus;

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
    public string $viewMode = 'kanban';

    public function mount(
        Collection $items,
        array $itemsByStatus,
        ?Carbon $currentDate = null,
        ?string $filterType = null,
        ?string $filterPriority = null,
        ?string $filterStatus = null,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        bool $hasActiveFilters = false,
        string $viewMode = 'kanban'
    ): void {
        $this->items = $items;
        $this->itemsByStatus = $itemsByStatus ?? ['to_do' => [], 'doing' => [], 'done' => []];
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

<div>
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
        mb
    />

    <!-- Loading overlay for status updates -->
    <div
        x-data="{ isUpdating: false }"
        x-on:update-item-status.window="isUpdating = true"
        x-on:item-updated.window="setTimeout(() => { isUpdating = false; }, 300)"
        x-show="isUpdating"
        x-transition
        class="fixed inset-0 bg-black/10 dark:bg-black/20 z-40 flex items-center justify-center pointer-events-none"
        style="display: none;"
    >
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-lg flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Updating status...</span>
        </div>
    </div>

    <div wire:loading.class="opacity-50"
         wire:target="goToTodayDate,previousDay,nextDay"
         wire:key="kanban-container">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4" aria-label="Kanban board">
    @foreach(['to_do', 'doing', 'done'] as $status)
        <div wire:key="kanban-column-{{ $status }}" class="bg-zinc-100 dark:bg-zinc-900 rounded-lg p-4"
             draggable="false"
             role="region"
             aria-label="{{ match($status) {
                 'to_do' => 'To Do',
                 'doing' => 'In Progress',
                 'done' => 'Done',
             } }} column"
             x-data="{ draggingOver: false, isUpdating: false }"
             @dragover.prevent="draggingOver = true"
             @dragleave="draggingOver = false"
             @drop.prevent="
                 draggingOver = false;
                 const itemId = $event.dataTransfer.getData('itemId');
                 const itemType = $event.dataTransfer.getData('itemType');

                 // Validate itemId exists and is valid
                 if (!itemId || itemId === '' || isNaN(parseInt(itemId))) {
                     return;
                 }

                 const parsedItemId = parseInt(itemId);
                 if (parsedItemId <= 0) {
                     return;
                 }

                 // Prevent events from being dropped in 'doing' column
                 if (itemType === 'event' && '{{ $status }}' === 'doing') {
                     return;
                 }

                 isUpdating = true;
                 $dispatch('update-item-status', {
                     itemId: parsedItemId,
                     itemType: itemType,
                     newStatus: '{{ $status }}'
                 });

                 // Reset updating state after a delay
                 setTimeout(() => { isUpdating = false; }, 1000);
             "
             :class="{ 'ring-2 ring-blue-500': draggingOver }"
        >
            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-4 select-none" draggable="false">
                {{ match($status) {
                    'to_do' => 'To Do',
                    'doing' => 'In Progress',
                    'done' => 'Done',
                } }}
                <span class="text-sm text-zinc-500 dark:text-zinc-400 ml-2" aria-label="{{ collect($this->itemsByStatus[$status] ?? [])->count() }} items">
                    ({{ collect($this->itemsByStatus[$status] ?? [])->count() }})
                </span>
            </h3>

            <div class="space-y-3" aria-live="polite">
                @foreach(collect($this->itemsByStatus[$status] ?? []) as $item)
                    <div
                        wire:key="item-{{ $item->item_type }}-{{ $item->id }}"
                        draggable="true"
                        role="button"
                        aria-label="Drag {{ $item->title ?? $item->name }} to move"
                        tabindex="0"
                        @dragstart="
                            $event.dataTransfer.effectAllowed = 'move';
                            $event.dataTransfer.setData('itemId', {{ $item->id }});
                            $event.dataTransfer.setData('itemType', '{{ $item->item_type }}');
                            $el.classList.add('opacity-50', 'ring-2', 'ring-blue-500');
                        "
                        @dragend="
                            $el.classList.remove('opacity-50', 'ring-2', 'ring-blue-500');
                        "
                        class="cursor-move relative transition-all"
                    >
                        @if($item->item_type === 'task')
                            <x-workspace.task-card :task="$item" />
                        @elseif($item->item_type === 'event')
                            <x-workspace.event-card :event="$item" />
                        @elseif($item->item_type === 'project')
                            <x-workspace.project-card :project="$item" />
                        @endif
                        <!-- Loading indicator -->
                        <div
                            x-data="{ isUpdating: false }"
                            x-on:update-item-status.window="if ($event.detail.itemId === {{ $item->id }}) { isUpdating = true; }"
                            x-on:item-updated.window="setTimeout(() => { isUpdating = false; }, 300)"
                            x-show="isUpdating"
                            x-transition
                            class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-zinc-900/50 rounded"
                            style="display: none;"
                        >
                            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                @endforeach

                @if($status === 'to_do')
                    <!-- Create New Item CTA -->
                    <button
                        wire:click="$dispatch('open-create-modal')"
                        class="w-full bg-white dark:bg-zinc-800 rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600 hover:border-blue-400 dark:hover:border-blue-500 p-6 transition-all hover:bg-zinc-50 dark:hover:bg-zinc-700/50 flex items-center justify-center group cursor-pointer"
                        draggable="false"
                        aria-label="Create new item"
                    >
                        <svg class="w-6 h-6 text-zinc-400 dark:text-zinc-500 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                @endif

                @if(collect($this->itemsByStatus[$status] ?? [])->isEmpty() && $status !== 'to_do')
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400 text-sm select-none" draggable="false" aria-label="Empty column">
                        No items
                    </div>
                @endif
            </div>
        </div>
    @endforeach
        </div>
    </div>
</div>

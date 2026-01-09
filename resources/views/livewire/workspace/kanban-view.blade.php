<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component
{
    #[Reactive]
    public Collection $items;

    #[Reactive]
    public array $itemsByStatus;

    #[Reactive]
    public $currentDate = null;

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
        $currentDate = null,
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
        mb
    />

    <div wire:loading.class="opacity-50"
         wire:target="goToTodayDate,previousDay,nextDay"
         wire:key="kanban-container">
        <div class="space-y-4">
            <!-- Create New Item CTA -->
            <button
                wire:click="$dispatch('open-create-modal')"
                class="w-full bg-white dark:bg-zinc-800 rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600 hover:border-blue-400 dark:hover:border-blue-500 p-8 transition-all hover:bg-zinc-50 dark:hover:bg-zinc-700/50 flex items-center justify-center group cursor-pointer"
                draggable="false"
                aria-label="Create new item"
            >
                <svg class="w-8 h-8 text-zinc-400 dark:text-zinc-500 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>

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
             x-data="{ draggingOver: false }"
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

                 // Send update to Livewire (server-side status change)
                 $dispatch('update-item-status', {
                     itemId: parsedItemId,
                     itemType: itemType,
                     newStatus: '{{ $status }}'
                 });
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
                            $event.dataTransfer.setData('label', '{{ $item->title ?? $item->name }}');
                            $el.classList.add('opacity-50', 'ring-2', 'ring-blue-500');
                        "
                        @dragend="
                            $el.classList.remove('opacity-50', 'ring-2', 'ring-blue-500');
                        "
                        x-transition.opacity
                        class="cursor-move relative transition-all"
                    >
                        @if($item->item_type === 'task')
                            <x-workspace.task-card :task="$item" />
                        @elseif($item->item_type === 'event')
                            <x-workspace.event-card :event="$item" />
                        @endif
                    </div>
                @endforeach

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
</div>

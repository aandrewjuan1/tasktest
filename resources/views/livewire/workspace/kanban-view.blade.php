<?php

use Carbon\Carbon;
use Illuminate\Support\Collection;
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

    public function mount(Collection $items, array $itemsByStatus, ?Carbon $currentDate = null): void
    {
        $this->items = $items;
        $this->itemsByStatus = $itemsByStatus;
        $this->currentDate = $currentDate ?? now();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    #[On('item-updated')]
    #[On('task-updated')]
    #[On('event-updated')]
    public function refreshItems(): void
    {
        // Force component to re-render and re-read reactive properties from parent
        // Accessing the reactive properties ensures they are updated from the parent
        $this->items;
        $this->itemsByStatus;
    }

    public function goToTodayDate(): void
    {
        $newDate = now();
        $this->dispatch('date-focused', date: $newDate->format('Y-m-d'));
    }

    public function previousDay(): void
    {
        $newDate = $this->currentDate->copy()->subDay();
        $this->dispatch('date-focused', date: $newDate->format('Y-m-d'));
    }

    public function nextDay(): void
    {
        $newDate = $this->currentDate->copy()->addDay();
        $this->dispatch('date-focused', date: $newDate->format('Y-m-d'));
    }
}; ?>

<div>
    <!-- Date Navigation -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden mb-4">
        <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $currentDate->format('M d, Y') }}
                </h3>
            </div>
            <div class="flex items-center gap-2">
                <flux:button variant="ghost" size="sm" wire:click="goToTodayDate">
                    Today
                </flux:button>
                <flux:button variant="ghost" size="sm" icon="chevron-left" wire:click="previousDay">
                </flux:button>
                <flux:button variant="ghost" size="sm" icon="chevron-right" wire:click="nextDay">
                </flux:button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @foreach(['to_do', 'doing', 'done'] as $status)
        <div class="bg-zinc-100 dark:bg-zinc-900 rounded-lg p-4"
             x-data="{ draggingOver: false }"
             @dragover.prevent="draggingOver = true"
             @dragleave="draggingOver = false"
             @drop.prevent="
                 draggingOver = false;
                 const itemId = $event.dataTransfer.getData('itemId');
                 const itemType = $event.dataTransfer.getData('itemType');

                 // Prevent events from being dropped in 'doing' column
                 if (itemType === 'event' && '{{ $status }}' === 'doing') {
                     return;
                 }

                 $dispatch('update-item-status', {
                     itemId: parseInt(itemId),
                     itemType: itemType,
                     newStatus: '{{ $status }}'
                 });
             "
             :class="{ 'ring-2 ring-blue-500': draggingOver }"
        >
            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                {{ match($status) {
                    'to_do' => 'To Do',
                    'doing' => 'In Progress',
                    'done' => 'Done',
                } }}
                <span class="text-sm text-zinc-500 dark:text-zinc-400 ml-2">
                    ({{ collect($itemsByStatus[$status])->count() }})
                </span>
            </h3>

            <div class="space-y-3">
                @foreach(collect($itemsByStatus[$status]) as $item)
                    <div
                        wire:key="item-{{ $item->item_type }}-{{ $item->id }}"
                        draggable="true"
                        @dragstart="
                            $event.dataTransfer.effectAllowed = 'move';
                            $event.dataTransfer.setData('itemId', {{ $item->id }});
                            $event.dataTransfer.setData('itemType', '{{ $item->item_type }}');
                        "
                        class="cursor-move"
                    >
                        @if($item->item_type === 'task')
                            <x-workspace.task-card :task="$item" />
                        @elseif($item->item_type === 'event')
                            <x-workspace.event-card :event="$item" />
                        @elseif($item->item_type === 'project')
                            <x-workspace.project-card :project="$item" />
                        @endif
                    </div>
                @endforeach

                @if(collect($itemsByStatus[$status])->isEmpty())
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400 text-sm">
                        No items
                    </div>
                @endif
            </div>
        </div>
    @endforeach
    </div>
</div>

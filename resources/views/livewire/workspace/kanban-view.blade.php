<?php

use Carbon\Carbon;
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
    public ?Carbon $currentDate = null;

    public function mount(Collection $items, array $itemsByStatus, ?Carbon $currentDate = null): void
    {
        $this->items = $items;
        $this->itemsByStatus = $itemsByStatus;
        $this->currentDate = $currentDate ?? now();
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
            <div class="flex items-center gap-2"
                 role="group"
                 aria-label="Date navigation"
                 x-data="{
                     navigationTimeout: null,
                     navigateDate(action) {
                         clearTimeout(this.navigationTimeout);
                         this.navigationTimeout = setTimeout(() => {
                             if (action === 'today') {
                                 $wire.$parent.goToTodayDate();
                                 $dispatch('date-focused', { date: new Date().toISOString().split('T')[0] });
                             } else if (action === 'previous') {
                                 const currentDate = new Date('{{ $currentDate->format('Y-m-d') }}');
                                 currentDate.setDate(currentDate.getDate() - 1);
                                 $wire.$parent.previousDay();
                                 $dispatch('date-focused', { date: currentDate.toISOString().split('T')[0] });
                             } else if (action === 'next') {
                                 const currentDate = new Date('{{ $currentDate->format('Y-m-d') }}');
                                 currentDate.setDate(currentDate.getDate() + 1);
                                 $wire.$parent.nextDay();
                                 $dispatch('date-focused', { date: currentDate.toISOString().split('T')[0] });
                             }
                         }, 150);
                     }
                 }">
                <flux:button
                    variant="ghost"
                    size="sm"
                    @click="navigateDate('today')"
                    wire:loading.attr="disabled"
                    wire:target="goToTodayDate,previousDay,nextDay"
                    aria-label="Go to today"
                >
                    <span wire:loading.remove wire:target="goToTodayDate,previousDay,nextDay">Today</span>
                    <span wire:loading wire:target="goToTodayDate,previousDay,nextDay">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </flux:button>
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="chevron-left"
                    @click="navigateDate('previous')"
                    wire:loading.attr="disabled"
                    wire:target="goToTodayDate,previousDay,nextDay"
                    aria-label="Previous day"
                >
                </flux:button>
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="chevron-right"
                    @click="navigateDate('next')"
                    wire:loading.attr="disabled"
                    wire:target="goToTodayDate,previousDay,nextDay"
                    aria-label="Next day"
                >
                </flux:button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4" aria-label="Kanban board">
    @foreach(['to_do', 'doing', 'done'] as $status)
        <div class="bg-zinc-100 dark:bg-zinc-900 rounded-lg p-4"
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
                <span class="text-sm text-zinc-500 dark:text-zinc-400 ml-2" aria-label="{{ collect($itemsByStatus[$status])->count() }} items">
                    ({{ collect($itemsByStatus[$status])->count() }})
                </span>
            </h3>

            <div class="space-y-3" aria-live="polite">
                @foreach(collect($itemsByStatus[$status]) as $item)
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
                        @keydown.enter.prevent="
                            // Allow keyboard activation for drag (would need additional implementation)
                        "
                        class="cursor-move relative transition-all"
                        wire:loading.class="opacity-50 pointer-events-none"
                        wire:target="handleUpdateItemStatus"
                    >
                        @if($item->item_type === 'task')
                            <x-workspace.task-card :task="$item" />
                        @elseif($item->item_type === 'event')
                            <x-workspace.event-card :event="$item" />
                        @elseif($item->item_type === 'project')
                            <x-workspace.project-card :project="$item" />
                        @endif
                        <!-- Loading indicator -->
                        <div wire:loading wire:target="handleUpdateItemStatus" class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-zinc-900/50 rounded">
                            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                @endforeach

                @if(collect($itemsByStatus[$status])->isEmpty())
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400 text-sm select-none" draggable="false" aria-label="Empty column">
                        No items
                    </div>
                @endif
            </div>
        </div>
    @endforeach
    </div>
</div>

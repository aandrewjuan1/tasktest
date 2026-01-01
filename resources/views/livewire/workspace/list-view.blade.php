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
    public ?Carbon $currentDate = null;

    public function mount(Collection $items, ?Carbon $currentDate = null): void
    {
        $this->items = $items;
        $this->currentDate = $currentDate ?? now();
    }
}; ?>

<div class="space-y-4">
    <!-- Date Navigation -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
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

    <div wire:transition="fade" wire:loading.class="opacity-50" wire:target="goToTodayDate,previousDay,nextDay,updateCurrentDate">
        @forelse($this->items as $item)
        @if($item->item_type === 'task')
            <x-workspace.task-card :task="$item" />
        @elseif($item->item_type === 'event')
            <x-workspace.event-card :event="$item" />
        @elseif($item->item_type === 'project')
            <x-workspace.project-card :project="$item" />
        @endif
    @empty
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-12 text-center" aria-live="polite">
            <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">No items found</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Get started by creating a new task, event, or project.
            </p>
            <div class="mt-4">
                <flux:button
                    variant="primary"
                    wire:click="$parent.openCreateModal"
                    aria-label="Create a new item"
                >
                    Create Your First Item
                </flux:button>
            </div>
        </div>
    @endforelse
    </div>
</div>

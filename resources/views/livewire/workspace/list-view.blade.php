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
    public ?Carbon $currentDate = null;

    public function mount(Collection $items, ?Carbon $currentDate = null): void
    {
        $this->items = $items;
        $this->currentDate = $currentDate ?? now();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    #[On('item-updated')]
    #[On('task-updated')]
    #[On('event-updated')]
    public function refreshItems(): void
    {
        // Items are reactive, so they will automatically update from parent
        // This listener ensures the component refreshes when items change
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

<div class="space-y-4">
    <!-- Date Navigation -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
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

    @forelse($this->items as $item)
        @if($item->item_type === 'task')
            <x-workspace.task-card :task="$item" />
        @elseif($item->item_type === 'event')
            <x-workspace.event-card :event="$item" />
        @elseif($item->item_type === 'project')
            <x-workspace.project-card :project="$item" />
        @endif
    @empty
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">No items found</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Get started by creating a new task, event, or project.
            </p>
        </div>
    @endforelse
</div>

<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\Event;
use Carbon\Carbon;

new class extends Component {
    public bool $isOpen = false;
    public ?Event $event = null;
    public int $x = 0;
    public int $y = 0;

    #[On('show-event-popover')]
    public function showPopover(int $id, int $x = 0, int $y = 0): void
    {
        $this->event = Event::with(['tags'])
            ->findOrFail($id);

        $this->authorize('view', $this->event);

        $this->x = $x;
        $this->y = $y;
        $this->isOpen = true;
    }

    public function closePopover(): void
    {
        $this->isOpen = false;
        $this->event = null;
    }

}; ?>

<div>
    @if($isOpen && $event)
        <!-- Backdrop -->
        <div class="fixed inset-0 z-40" wire:click="closePopover"></div>

        <!-- Popover -->
        <div
            class="fixed z-50 bg-white dark:bg-zinc-800 rounded-lg shadow-xl border border-zinc-200 dark:border-zinc-700 p-4 min-w-[280px] max-w-[320px]"
            style="left: {{ min($x, 100) }}px; top: {{ min($y, 100) }}px;"
            @click.stop
            x-data="{ show: @entangle('isOpen') }"
            x-show="show"
            x-cloak
            x-transition
        >
            <!-- Header -->
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm mb-1">
                        {{ $event->title }}
                    </h3>
                    @if($event->description)
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2">
                            {{ $event->description }}
                        </p>
                    @endif
                </div>
                <button
                    wire:click="closePopover"
                    class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 ml-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Event Details -->
            <div class="space-y-2 mb-3">
                <!-- Date/Time -->
                <div class="flex items-start gap-2 text-xs">
                    <svg class="w-4 h-4 text-zinc-400 dark:text-zinc-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <div class="text-zinc-700 dark:text-zinc-300">
                        <div>{{ Carbon::parse($event->start_datetime)->format('l, F j, Y') }}</div>
                        <div class="text-zinc-500 dark:text-zinc-400">
                            {{ Carbon::parse($event->start_datetime)->format('g:i A') }}
                            @if(Carbon::parse($event->start_datetime)->format('Y-m-d') === Carbon::parse($event->end_datetime)->format('Y-m-d'))
                                - {{ Carbon::parse($event->end_datetime)->format('g:i A') }}
                            @else
                                - {{ Carbon::parse($event->end_datetime)->format('g:i A, M j') }}
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="flex items-center gap-2 text-xs">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        {{ match($event->status) {
                            'scheduled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            'tentative' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                            default => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200',
                        } }}">
                        {{ ucfirst($event->status) }}
                    </span>
                </div>

                <!-- Tags -->
                @if($event->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1">
                        @foreach($event->tags as $tag)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Actions -->
            <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button
                    variant="ghost"
                    size="sm"
                    wire:click="closePopover"
                    class="flex-1"
                >
                    Close
                </flux:button>
            </div>
        </div>
    @endif
</div>

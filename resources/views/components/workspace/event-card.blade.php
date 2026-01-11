@props(['event'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border border-blue-300 dark:border-blue-600 p-2.5 sm:p-3 cursor-pointer transition-all flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3"
    wire:click="$dispatch('view-event-detail', { id: {{ $event->id }} })"
    role="button"
    tabindex="0"
    aria-label="View event details: {{ $event->title }}"
>
    {{-- First Row: Title, Tags, Status Buttons, and Badges --}}
    <div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0 mb-2 sm:mb-0">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-base sm:text-lg lg:text-xl leading-tight flex-1 min-w-0 flex items-center gap-1.5 sm:gap-2 flex-wrap">
            <span class="truncate">{{ $event->title }}</span>
            @if($event->tags->isNotEmpty())
                <span class="flex items-center gap-0.5 sm:gap-1 flex-shrink-0" @click.stop>
                    @foreach($event->tags->take(2) as $tag)
                        <span
                            class="inline-flex items-center px-0.5 sm:px-1 py-0 text-[10px] sm:text-xs rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400"
                        >
                            {{ $tag->name }}
                        </span>
                    @endforeach
                    @if($event->tags->count() > 2)
                        <span class="inline-flex items-center px-0.5 sm:px-1 py-0 text-[10px] sm:text-xs rounded bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-500">
                            +{{ $event->tags->count() - 2 }}
                        </span>
                    @endif
                </span>
            @endif
        </h3>

        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0" @click.stop>
            <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 text-xs font-medium rounded-md bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                Event
            </span>
            @if($event->recurringEvent)
                <span
                    class="inline-flex items-center gap-1 px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                    title="Recurring: {{ ucfirst($event->recurringEvent->recurrence_type->value) }}"
                >
                    <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="hidden sm:inline">Recurring</span>
                </span>
            @endif
            @if($event->status)
                <span
                    class="inline-flex items-center px-1.5 sm:px-2 py-0.5 text-xs font-medium rounded-md {{ $event->status->badgeColor() }}"
                >
                    {{ ucfirst($event->status->value) }}
                </span>
            @endif

            @if($event->status)
                <div class="flex items-center gap-0.5 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-md p-0.5">
                @php
                    $currentStatus = $event->status->value;
                    $availableStatuses = match($currentStatus) {
                        'scheduled' => [
                            ['status' => 'ongoing', 'icon' => 'play', 'tooltip' => 'Mark as Ongoing'],
                            ['status' => 'completed', 'icon' => 'check', 'tooltip' => 'Mark as Completed'],
                            ['status' => 'cancelled', 'icon' => 'archive-box-x-mark', 'tooltip' => 'Mark as Cancelled'],
                        ],
                        'ongoing' => [
                            ['status' => 'scheduled', 'icon' => 'clock', 'tooltip' => 'Mark as Scheduled'],
                            ['status' => 'completed', 'icon' => 'check', 'tooltip' => 'Mark as Completed'],
                            ['status' => 'cancelled', 'icon' => 'archive-box-x-mark', 'tooltip' => 'Mark as Cancelled'],
                        ],
                        'completed' => [
                            ['status' => 'scheduled', 'icon' => 'clock', 'tooltip' => 'Mark as Scheduled'],
                            ['status' => 'ongoing', 'icon' => 'play', 'tooltip' => 'Mark as Ongoing'],
                            ['status' => 'cancelled', 'icon' => 'archive-box-x-mark', 'tooltip' => 'Mark as Cancelled'],
                        ],
                        'cancelled' => [
                            ['status' => 'scheduled', 'icon' => 'clock', 'tooltip' => 'Mark as Scheduled'],
                            ['status' => 'ongoing', 'icon' => 'play', 'tooltip' => 'Mark as Ongoing'],
                            ['status' => 'completed', 'icon' => 'check', 'tooltip' => 'Mark as Completed'],
                        ],
                        'tentative' => [
                            ['status' => 'scheduled', 'icon' => 'clock', 'tooltip' => 'Mark as Scheduled'],
                            ['status' => 'ongoing', 'icon' => 'play', 'tooltip' => 'Mark as Ongoing'],
                            ['status' => 'completed', 'icon' => 'check', 'tooltip' => 'Mark as Completed'],
                        ],
                        default => [],
                    };
                @endphp
                @foreach($availableStatuses as $statusOption)
                    <flux:button
                        variant="ghost"
                        size="xs"
                        icon="{{ $statusOption['icon'] }}"
                        tooltip="{{ $statusOption['tooltip'] }}"
                        @click="$dispatch('update-item-status', { itemId: {{ $event->id }}, itemType: 'event', newStatus: '{{ $statusOption['status'] }}' })"
                        class="!p-0.5 !w-5 !h-5 hover:bg-blue-50 dark:hover:bg-blue-900 rounded"
                    />
                @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Second Row on Mobile, Inline on Desktop: Dates, Badges, Tags --}}
    <div class="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap sm:flex-shrink-0">
        @if($event->start_datetime || $event->end_datetime)
            <div class="flex items-center gap-2 sm:gap-3 text-xs text-zinc-600 dark:text-zinc-400">
                @if($event->start_datetime)
                    <div class="flex items-center gap-1 sm:gap-1.5">
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="whitespace-nowrap hidden sm:inline">{{ $event->start_datetime->format('M j, g:i A') }}</span>
                        <span class="whitespace-nowrap sm:hidden">{{ $event->start_datetime->format('M j') }}</span>
                    </div>
                @endif

                @if($event->end_datetime)
                    <div class="flex items-center gap-1 sm:gap-1.5">
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="whitespace-nowrap hidden sm:inline">{{ $event->end_datetime->format('M j, g:i A') }}</span>
                        <span class="whitespace-nowrap sm:hidden">{{ $event->end_datetime->format('M j') }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

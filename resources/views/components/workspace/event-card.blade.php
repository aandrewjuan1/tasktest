@props(['event'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border border-blue-300 dark:border-blue-600 p-3 cursor-pointer transition-all flex items-center gap-3"
    wire:click="$dispatch('view-event-detail', { id: {{ $event->id }} })"
    role="button"
    tabindex="0"
    aria-label="View event details: {{ $event->title }}"
>
    @if($event->status)
        <div class="flex items-center gap-1 flex-shrink-0" @click.stop>
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
                    class="!p-1 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900"
                />
            @endforeach
        </div>
    @endif

    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm leading-tight truncate flex-1 min-w-0">
        {{ $event->title }}
    </h3>

    @if($event->start_datetime || $event->end_datetime)
        <div class="flex items-center gap-3 text-xs text-zinc-600 dark:text-zinc-400 flex-shrink-0">
            @if($event->start_datetime)
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="whitespace-nowrap">{{ $event->start_datetime->format('M j, g:i A') }}</span>
                </div>
            @endif

            @if($event->end_datetime)
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="whitespace-nowrap">{{ $event->end_datetime->format('M j, g:i A') }}</span>
                </div>
            @endif
        </div>
    @endif

    <div class="flex items-center gap-2 flex-shrink-0">
        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
            Event
        </span>
        @if($event->status)
            <span
                class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md {{ $event->status->badgeColor() }}"
            >
                {{ ucfirst($event->status->value) }}
            </span>
        @endif
    </div>

    @if($event->tags->isNotEmpty())
        <div class="flex items-center gap-1.5 flex-shrink-0">
            @foreach($event->tags->take(2) as $tag)
                <span
                    class="inline-flex items-center px-1.5 py-0.5 text-xs rounded-md bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    {{ $tag->name }}
                </span>
            @endforeach
            @if($event->tags->count() > 2)
                <span class="inline-flex items-center px-1.5 py-0.5 text-xs rounded-md bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                    +{{ $event->tags->count() - 2 }}
                </span>
            @endif
        </div>
    @endif
</div>

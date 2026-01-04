@props(['event'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border-l-4 border-zinc-200 dark:border-zinc-700 p-4 cursor-pointer hover:shadow-lg transition-all"
    wire:click="$dispatch('view-event-detail', { id: {{ $event->id }} })"
    role="button"
    tabindex="0"
    aria-label="View event details: {{ $event->title }}"
>
    <div class="flex items-center gap-2 mb-3">
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
            Event
        </span>
    </div>

    <div class="flex items-start justify-between gap-3 mb-3">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-2 flex-1">
            {{ $event->title }}
        </h3>
    </div>

    @if($event->description)
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3 line-clamp-2">
            {{ Str::limit($event->description, 100) }}
        </p>
    @endif

    <div class="flex flex-wrap gap-2 mb-3">
        @if($event->status)
            <span
                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $event->status->badgeColor() }}"
            >
                {{ ucfirst($event->status->value) }}
            </span>
        @endif
    </div>

    <div class="space-y-2 text-xs text-zinc-600 dark:text-zinc-400 mb-3">
        @if($event->start_datetime)
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                    {{ $event->start_datetime->format('M j, Y g:i A') }}
                </span>
            </div>
        @endif

        @if($event->end_datetime)
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $event->end_datetime->format('M j, Y g:i A') }}</span>
            </div>
        @elseif(!$event->end_datetime && $event->start_datetime)
            <span class="text-zinc-400 dark:text-zinc-500 text-xs">No end time</span>
        @endif
    </div>

    @if($event->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1 mb-3">
            @foreach($event->tags->take(3) as $tag)
                <span
                    class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    {{ $tag->name }}
                </span>
            @endforeach
            @if($event->tags->count() > 3)
                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                    +{{ $event->tags->count() - 3 }} more
                </span>
            @endif
        </div>
    @endif
</div>

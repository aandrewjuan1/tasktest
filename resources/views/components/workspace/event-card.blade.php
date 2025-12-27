@props(['event'])

<div class="bg-white dark:bg-zinc-800 rounded-lg border-l-4 border-zinc-200 dark:border-zinc-700 p-4 hover:shadow-md transition-shadow" style="border-left-color: {{ $event->color ?? '#6b7280' }}">
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
            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ match($event->status->value) {
                'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                'completed' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                'tentative' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
            } }}">
                {{ ucfirst($event->status->value) }}
            </span>
        @endif
    </div>

    <div class="space-y-2 text-xs text-zinc-600 dark:text-zinc-400 mb-3">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
                @if($event->all_day)
                    {{ $event->start_datetime->format('M j, Y') }} (All day)
                @else
                    {{ $event->start_datetime->format('M j, Y g:i A') }}
                @endif
            </span>
        </div>

        @if($event->location)
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="truncate">{{ $event->location }}</span>
            </div>
        @endif
    </div>

    @if($event->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1 mb-3">
            @foreach($event->tags->take(3) as $tag)
                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
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

    <div class="flex justify-end">
        <flux:button variant="ghost" size="sm" wire:click="$dispatch('view-event-detail', { id: {{ $event->id }} })">
            View Details
        </flux:button>
    </div>
</div>


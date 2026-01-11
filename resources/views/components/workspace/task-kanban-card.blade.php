@props(['task'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border-l-4 border-purple-500 dark:border-purple-400 border-r border-t border-b border-zinc-200 dark:border-zinc-700 p-2 sm:p-3 cursor-pointer hover:shadow-md hover:shadow-purple-100 dark:hover:shadow-purple-900/20 hover:border-purple-600 dark:hover:border-purple-300 transition-all flex flex-col"
    wire:click="$dispatch('view-task-detail', { id: {{ $task->id }} })"
    role="button"
    tabindex="0"
    aria-label="View task details: {{ $task->title }}"
>
    {{-- Header Section --}}
    <div class="mb-1.5 sm:mb-2">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm leading-tight line-clamp-2">
            {{ $task->title }}
        </h3>
    </div>

    {{-- Badges Row --}}
    <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5 sm:mb-2 flex-wrap">
        <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 text-xs font-medium rounded-md bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
            Task
        </span>
        @if($task->recurringTask)
            <span
                class="inline-flex items-center gap-0.5 px-1.5 sm:px-2 py-0.5 text-xs font-medium rounded-md bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                title="Recurring: {{ ucfirst($task->recurringTask->recurrence_type->value) }}"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="hidden sm:inline">Recurring</span>
            </span>
        @endif
        @if($task->priority)
            <span
                class="inline-flex items-center px-1.5 sm:px-2 py-0.5 text-xs font-medium rounded-md {{ $task->priority->badgeColor() }}"
            >
                <span class="hidden sm:inline">{{ ucfirst($task->priority->value) }} Priority</span>
                <span class="sm:hidden">{{ ucfirst($task->priority->value) }}</span>
            </span>
        @endif
    </div>

    {{-- DateTime Section --}}
    @if($task->end_datetime || $task->start_datetime)
        <div class="flex items-center gap-1.5 sm:gap-2 text-xs text-zinc-600 dark:text-zinc-400 mb-1.5 sm:mb-2">
            @if($task->end_datetime)
                <div class="flex items-center gap-1 {{ $task->end_datetime->isPast() && $task->status?->value !== 'done' ? 'text-red-600 dark:text-red-400' : '' }}">
                    <svg class="w-3 h-3 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="whitespace-nowrap hidden sm:inline {{ $task->end_datetime->isPast() && $task->status?->value !== 'done' ? 'font-semibold' : '' }}">{{ $task->end_datetime->format('M j, g:i A') }}</span>
                    <span class="whitespace-nowrap sm:hidden {{ $task->end_datetime->isPast() && $task->status?->value !== 'done' ? 'font-semibold' : '' }}">{{ $task->end_datetime->format('M j') }}</span>
                </div>
            @elseif($task->start_datetime)
                <div class="flex items-center gap-1">
                    <svg class="w-3 h-3 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="whitespace-nowrap hidden sm:inline">{{ $task->start_datetime->format('M j, g:i A') }}</span>
                    <span class="whitespace-nowrap sm:hidden">{{ $task->start_datetime->format('M j') }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Tags Section --}}
    @if($task->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1 pt-1.5 sm:pt-2 border-t border-zinc-200 dark:border-zinc-700">
            @foreach($task->tags->take(2) as $tag)
                <span
                    class="inline-flex items-center px-1 sm:px-1.5 py-0.5 text-xs rounded-md bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    {{ $tag->name }}
                </span>
            @endforeach
            @if($task->tags->count() > 2)
                <span class="inline-flex items-center px-1 sm:px-1.5 py-0.5 text-xs rounded-md bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                    +{{ $task->tags->count() - 2 }}
                </span>
            @endif
        </div>
    @endif
</div>

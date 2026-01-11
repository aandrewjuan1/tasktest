@props(['task'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border-l-4 border-purple-500 dark:border-purple-400 border-r border-t border-b border-zinc-200 dark:border-zinc-700 p-3 sm:p-5 cursor-pointer hover:shadow-md hover:shadow-purple-100 dark:hover:shadow-purple-900/20 hover:border-purple-600 dark:hover:border-purple-300 transition-all flex flex-col h-full"
    wire:click="$dispatch('view-task-detail', { id: {{ $task->id }} })"
    role="button"
    tabindex="0"
    aria-label="View task details: {{ $task->title }}"
>
    {{-- Header Section --}}
    <div class="mb-4">
        {{-- First Row: Title, Status Buttons, and Badges --}}
        <div class="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-2">
            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-base sm:text-lg lg:text-xl leading-tight flex-1 min-w-0 flex items-center gap-1.5 sm:gap-2 flex-wrap">
                <span class="line-clamp-2">{{ $task->title }}</span>
                @if($task->tags->isNotEmpty())
                    <span class="flex items-center gap-0.5 sm:gap-1 flex-shrink-0" @click.stop>
                        @foreach($task->tags->take(3) as $tag)
                            <span
                                class="inline-flex items-center px-0.5 sm:px-1 py-0 text-[10px] sm:text-xs rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400"
                            >
                                {{ $tag->name }}
                            </span>
                        @endforeach
                        @if($task->tags->count() > 3)
                            <span class="inline-flex items-center px-0.5 sm:px-1 py-0 text-[10px] sm:text-xs rounded bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-500">
                                +{{ $task->tags->count() - 3 }}
                            </span>
                        @endif
                    </span>
                @endif
            </h3>

            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0" @click.stop>
                <span class="inline-flex items-center px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                    Task
                </span>
                @if($task->status)
                    <span
                        class="inline-flex items-center px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md {{ $task->status->badgeColor() }}"
                    >
                        {{ match($task->status->value) {
                            'to_do' => 'To Do',
                            'doing' => 'In Progress',
                            'done' => 'Done',
                        } }}
                    </span>
                @endif

                @if($task->status)
                    <div class="flex items-center gap-0.5 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-md p-0.5">
                    @php
                        $currentStatus = $task->status->value;
                        $availableStatuses = match($currentStatus) {
                            'to_do' => [
                                ['status' => 'doing', 'icon' => 'play', 'tooltip' => 'Mark as In Progress'],
                                ['status' => 'done', 'icon' => 'check', 'tooltip' => 'Mark as Done'],
                            ],
                            'doing' => [
                                ['status' => 'to_do', 'icon' => 'arrow-down-circle', 'tooltip' => 'Mark as To Do'],
                                ['status' => 'done', 'icon' => 'check', 'tooltip' => 'Mark as Done'],
                            ],
                            'done' => [
                                ['status' => 'to_do', 'icon' => 'arrow-down-circle', 'tooltip' => 'Mark as To Do'],
                                ['status' => 'doing', 'icon' => 'play', 'tooltip' => 'Mark as In Progress'],
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
                            @click="$dispatch('update-item-status', { itemId: {{ $task->id }}, itemType: 'task', newStatus: '{{ $statusOption['status'] }}' })"
                            class="!p-0.5 !w-5 !h-5 hover:bg-blue-50 dark:hover:bg-blue-900 rounded"
                        />
                    @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Second Row: Dates --}}
        @if($task->start_datetime || $task->end_datetime)
            <div class="flex items-center gap-2 sm:gap-3 text-xs text-zinc-600 dark:text-zinc-400 mb-2 sm:mb-0">
                @if($task->start_datetime)
                    <div class="flex items-center gap-1 sm:gap-1.5">
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="whitespace-nowrap hidden sm:inline">{{ $task->start_datetime->format('M j, g:i A') }}</span>
                        <span class="whitespace-nowrap sm:hidden">{{ $task->start_datetime->format('M j') }}</span>
                    </div>
                @endif

                @if($task->end_datetime)
                    <div class="flex items-center gap-1 sm:gap-1.5 {{ $task->end_datetime->isPast() && $task->status?->value !== 'done' ? 'text-red-600 dark:text-red-400' : '' }}">
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="whitespace-nowrap hidden sm:inline {{ $task->end_datetime->isPast() && $task->status?->value !== 'done' ? 'font-semibold' : '' }}">{{ $task->end_datetime->format('M j, g:i A') }}</span>
                        <span class="whitespace-nowrap sm:hidden {{ $task->end_datetime->isPast() && $task->status?->value !== 'done' ? 'font-semibold' : '' }}">{{ $task->end_datetime->format('M j') }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Description Section --}}
    @if($task->description)
        <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mb-3 sm:mb-4 line-clamp-2 leading-relaxed">
            {{ Str::limit($task->description, 100) }}
        </p>
    @endif

    {{-- Badges Section --}}
    <div class="flex flex-wrap gap-1.5 sm:gap-2 mb-3 sm:mb-4">
        @if($task->recurringTask)
            <span
                class="inline-flex items-center gap-1 px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                title="Recurring: {{ ucfirst($task->recurringTask->recurrence_type->value) }}"
            >
                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="hidden sm:inline">Recurring</span>
            </span>
        @endif

        @if($task->priority)
            <span
                class="inline-flex items-center px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md {{ $task->priority->badgeColor() }}"
            >
                <span class="hidden sm:inline">{{ ucfirst($task->priority->value) }} Priority</span>
                <span class="sm:hidden">{{ ucfirst($task->priority->value) }}</span>
            </span>
        @endif

        @if($task->complexity)
            <span
                class="inline-flex items-center px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md {{ $task->complexity->badgeColor() }}"
            >
                {{ ucfirst($task->complexity->value) }}
            </span>
        @endif

        @if($task->duration)
            <span
                class="inline-flex items-center px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200"
            >
                {{ $task->duration }} min
            </span>
        @endif
    </div>

    {{-- Metadata Section --}}
    @if($task->project || $task->event)
        <div class="space-y-2 sm:space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 mb-3 sm:mb-4 flex-1">
            <div class="flex flex-col gap-1.5 sm:gap-2 pt-1.5 sm:pt-2 border-t border-zinc-200 dark:border-zinc-700">
                @if($task->project)
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span class="truncate font-medium">{{ $task->project->name }}</span>
                    </div>
                @endif

                @if($task->event)
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 16h14M7 4h.01M7 20h.01" />
                        </svg>
                        <span class="truncate">
                            <span class="font-medium">Event:</span> {{ $task->event->title }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>

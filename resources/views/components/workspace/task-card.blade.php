@props(['task'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 cursor-pointer hover:shadow-lg hover:border-blue-300 dark:hover:border-blue-600 transition-all"
    wire:click="$dispatch('view-task-detail', { id: {{ $task->id }} })"
    role="button"
    tabindex="0"
    aria-label="View task details: {{ $task->title }}"
>
    <div class="flex items-center gap-2 mb-3">
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
            Task
        </span>
    </div>

    <div class="flex items-start justify-between gap-3 mb-3 relative">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-2 flex-1">
            {{ $task->title }}
        </h3>
        @if($task->status)
            <div class="flex items-center gap-1 flex-shrink-0" @click.stop>
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
                        class="!p-1"
                    />
                @endforeach
            </div>
        @endif
    </div>

    @if($task->description)
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3 line-clamp-2">
            {{ Str::limit($task->description, 100) }}
        </p>
    @endif

    <div class="flex flex-wrap gap-2 mb-3">
        @if($task->status)
            <span
                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $task->status->badgeColor() }}"
            >
                {{ match($task->status->value) {
                    'to_do' => 'To Do',
                    'doing' => 'In Progress',
                    'done' => 'Done',
                } }}
            </span>
        @endif

        @if($task->priority)
            <span
                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $task->priority->badgeColor() }}"
            >
                {{ ucfirst($task->priority->value) }} Priority
            </span>
        @endif

        @if($task->complexity)
            <span
                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $task->complexity->badgeColor() }}"
            >
                {{ ucfirst($task->complexity->value) }}
            </span>
        @endif
    </div>

    <div class="space-y-2 text-xs text-zinc-600 dark:text-zinc-400 mb-3">
        @if($task->start_datetime)
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">Start:</span>
                <span>{{ $task->start_datetime->format('M j, Y g:i A') }}</span>
            </div>
        @endif

        @if($task->end_datetime)
            <div class="flex items-center gap-2 {{ $task->end_datetime->isPast() && $task->status?->value !== 'done' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="font-medium">Due:</span>
                <span>{{ $task->end_datetime->format('M j, Y g:i A') }}</span>
            </div>
        @elseif(!$task->start_datetime)
            <span class="text-zinc-400 dark:text-zinc-500 text-xs">No dates set</span>
        @endif

        @if($task->project)
            <div class="flex items-center gap-2 pt-1">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
                <span class="truncate">{{ $task->project->name }}</span>
            </div>
        @endif
    </div>

    @if($task->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1 mb-3">
            @foreach($task->tags->take(3) as $tag)
                <span
                    class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    {{ $tag->name }}
                </span>
            @endforeach
            @if($task->tags->count() > 3)
                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                    +{{ $task->tags->count() - 3 }} more
                </span>
            @endif
        </div>
    @endif
</div>

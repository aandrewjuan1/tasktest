@props(['task'])

<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between gap-3 mb-3">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-2 flex-1">
            {{ $task->title }}
        </h3>

        @if($task->priority)
            <span
                class="flex-shrink-0 w-3 h-3 rounded-full {{ match($task->priority->value) {
                    'low' => 'bg-zinc-400',
                    'medium' => 'bg-yellow-400',
                    'high' => 'bg-orange-500',
                    'urgent' => 'bg-red-500',
                } }}"
                title="{{ ucfirst($task->priority->value) }} priority"
            ></span>
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
                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ match($task->status->value) {
                    'to_do' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300',
                    'doing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                    'done' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                } }}"
            >
                {{ match($task->status->value) {
                    'to_do' => 'To Do',
                    'doing' => 'In Progress',
                    'done' => 'Done',
                } }}
            </span>
        @endif

        @if($task->complexity)
            <span
                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ match($task->complexity->value) {
                    'simple' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                    'moderate' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                    'complex' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                } }}"
            >
                {{ ucfirst($task->complexity->value) }}
            </span>
        @endif
    </div>

    <div class="flex items-center gap-4 text-xs text-zinc-600 dark:text-zinc-400 mb-3">
        @if($task->end_date)
            <div class="flex items-center gap-1 {{ $task->end_date->isPast() && $task->status?->value !== 'done' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{{ $task->end_date->format('M j, Y') }}</span>
            </div>
        @else
            <span class="text-zinc-400 dark:text-zinc-500 text-xs">No due date</span>
        @endif

        @if($task->project)
            <div class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    <div class="flex justify-end">
        <flux:button variant="ghost" size="sm" wire:click="$dispatch('view-task-detail', { id: {{ $task->id }} })">
            View Details
        </flux:button>
    </div>
</div>

@props(['project'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 transition-all"
>
    <div class="flex items-start justify-between gap-3 mb-3">
        <div class="flex items-start gap-2 flex-1 min-w-0">
            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-base sm:text-lg leading-snug line-clamp-2">
                {{ $project->name }}
            </h3>
        </div>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 flex-shrink-0">
            Project
        </span>
    </div>

    @if($project->description)
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3 line-clamp-2">
            {{ Str::limit($project->description, 100) }}
        </p>
    @endif

    <div class="space-y-2 text-xs text-zinc-600 dark:text-zinc-400 mb-3">
        @if($project->start_datetime)
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">Start:</span>
                <span>{{ $project->start_datetime->format('M j, Y g:i A') }}</span>
            </div>
        @endif

        @if($project->end_datetime)
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="font-medium">End:</span>
                <span>{{ $project->end_datetime->format('M j, Y g:i A') }}</span>
            </div>
        @elseif(!$project->start_datetime)
            <span class="text-zinc-400 dark:text-zinc-500 text-xs">No dates set</span>
        @endif
    </div>

    @php
        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', App\Enums\TaskStatus::Done)->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
    @endphp

    <div class="mb-3">
        <div class="flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-400 mb-1">
            <span>{{ $completedTasks }} of {{ $totalTasks }} tasks completed</span>
            <span>{{ $progress }}%</span>
        </div>
        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
            <div class="bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
        </div>
    </div>

    @if($project->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1 mb-3">
            @foreach($project->tags->take(3) as $tag)
                <span
                    class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    {{ $tag->name }}
                </span>
            @endforeach
            @if($project->tags->count() > 3)
                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                    +{{ $project->tags->count() - 3 }} more
                </span>
            @endif
        </div>
    @endif

    @if($project->tasks->isNotEmpty())
        <div class="mt-2 space-y-1">
            @foreach($project->tasks->take(3) as $task)
                <div class="flex items-center justify-between rounded bg-zinc-50 dark:bg-zinc-900 px-2 py-1.5">
                    <p class="text-xs font-medium text-zinc-800 dark:text-zinc-100 truncate">
                        {{ $task->title }}
                    </p>
                    @if($task->status)
                        <span class="inline-flex items-center px-2 py-0.5 text-[0.7rem] font-medium rounded {{ $task->status->badgeColor() }}">
                            {{ match($task->status->value) {
                                'to_do' => 'To Do',
                                'doing' => 'In Progress',
                                'done' => 'Done',
                            } }}
                        </span>
                    @endif
                </div>
            @endforeach

            @if($project->tasks->count() > 3)
                <p class="text-[0.7rem] text-zinc-500 dark:text-zinc-400">
                    +{{ $project->tasks->count() - 3 }} more tasks
                </p>
            @endif
        </div>
    @endif
</div>

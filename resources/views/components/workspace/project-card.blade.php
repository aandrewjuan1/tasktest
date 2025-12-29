@props(['project'])

<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between gap-3 mb-3">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-2 flex-1">
            {{ $project->name }}
        </h3>
    </div>

    @if($project->description)
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3 line-clamp-2">
            {{ Str::limit($project->description, 100) }}
        </p>
    @endif

    @if($project->start_date || $project->end_date)
        <div class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400 mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>
                @if($project->start_date && $project->end_date)
                    {{ $project->start_date->format('M j') }} - {{ $project->end_date->format('M j, Y') }}
                @elseif($project->start_date)
                    From {{ $project->start_date->format('M j, Y') }}
                @else
                    Until {{ $project->end_date->format('M j, Y') }}
                @endif
            </span>
        </div>
    @endif

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
                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
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

    <div class="flex justify-end">
        <flux:button variant="ghost" size="sm" wire:click="$dispatch('view-project-detail', { id: {{ $project->id }} })">
            View Details
        </flux:button>
    </div>
</div>



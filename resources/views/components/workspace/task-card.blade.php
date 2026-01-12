@props(['task'])

<div
    class="bg-white dark:bg-zinc-800 rounded-lg border-l-4 border-l-purple-500 border-r border-t border-b border-zinc-200 dark:border-zinc-700 p-3 sm:p-5 transition-all flex flex-col h-full"
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
                @php
                    $statusColors = [
                        'to_do' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600',
                        'doing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800',
                        'done' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800',
                    ];
                @endphp
                <x-inline-edit-dropdown
                    field="status"
                    :item-id="$task->id"
                    :use-parent="true"
                    :value="$task->status?->value ?? 'to_do'"
                    dropdown-class="w-48"
                    trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full transition-colors cursor-pointer text-xs font-medium"
                    :color-map="$statusColors"
                    default-color-class="bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600"
                >
                    <x-slot:trigger>
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                        </svg>
                        <span
                            x-text="{
                                to_do: 'To Do',
                                doing: 'In Progress',
                                done: 'Done',
                            }[selectedValue || 'to_do']"
                        >{{ match($task->status?->value ?? 'to_do') {
                            'to_do' => 'To Do',
                            'doing' => 'In Progress',
                            'done' => 'Done',
                        } }}</span>
                    </x-slot:trigger>

                    <x-slot:options>
                        <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                            Status
                        </div>
                        <button
                            @click="select('to_do')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'to_do' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            To Do
                        </button>
                        <button
                            @click="select('doing')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'doing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            In Progress
                        </button>
                        <button
                            @click="select('done')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'done' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            Done
                        </button>
                    </x-slot:options>
                </x-inline-edit-dropdown>
            </div>
        </div>

        {{-- Second Row: Dates --}}
        <div class="flex items-center gap-1.5 sm:gap-2 mb-2 sm:mb-0 flex-wrap" @click.stop>
            <x-workspace.inline-edit-date-picker
                field="startDatetime"
                :item-id="$task->id"
                :value="$task->start_datetime?->toIso8601String()"
                label="Start"
                type="datetime-local"
                trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-xs font-medium"
            />

            <x-workspace.inline-edit-date-picker
                field="endDatetime"
                :item-id="$task->id"
                :value="$task->end_datetime?->toIso8601String()"
                label="Due"
                type="datetime-local"
                trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-xs font-medium"
            />
        </div>
    </div>

    {{-- Description Section --}}
    @if($task->description)
        <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mb-3 sm:mb-4 line-clamp-2 leading-relaxed">
            {{ Str::limit($task->description, 100) }}
        </p>
    @endif

    {{-- Badges Section --}}
    <div class="flex flex-wrap gap-1.5 sm:gap-2 mb-3 sm:mb-4" @click.stop>
        {{-- Recurrence --}}
        <x-workspace.inline-edit-recurrence
            :item-id="$task->id"
            :recurring-task="$task->recurringTask"
            trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/40 transition-colors cursor-pointer text-xs font-medium"
        />

        {{-- Priority --}}
        @php
            $priorityColors = [
                'low' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600',
                'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-800',
                'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300 hover:bg-orange-200 dark:hover:bg-orange-800',
                'urgent' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800',
            ];
        @endphp
        <x-inline-edit-dropdown
            field="priority"
            :item-id="$task->id"
            :use-parent="true"
            :value="$task->priority?->value ?? ''"
            dropdown-class="w-48"
            trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full transition-colors cursor-pointer text-xs font-medium"
            :color-map="$priorityColors"
            default-color-class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700"
        >
            <x-slot:trigger>
                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span
                    x-text="{
                        low: 'Low',
                        medium: 'Medium',
                        high: 'High',
                        urgent: 'Urgent'
                    }[selectedValue || ''] || 'Priority'"
                >{{ $task->priority ? ucfirst($task->priority->value) . ' Priority' : 'Priority' }}</span>
            </x-slot:trigger>

            <x-slot:options>
                <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    Priority
                </div>
                <button
                    @click="select('low')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    :class="selectedValue === 'low' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                >
                    Low
                </button>
                <button
                    @click="select('medium')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    :class="selectedValue === 'medium' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                >
                    Medium
                </button>
                <button
                    @click="select('high')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    :class="selectedValue === 'high' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                >
                    High
                </button>
                <button
                    @click="select('urgent')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    :class="selectedValue === 'urgent' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                >
                    Urgent
                </button>
            </x-slot:options>
        </x-inline-edit-dropdown>

        {{-- Complexity --}}
        @php
            $complexityColors = [
                'simple' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800',
                'moderate' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-800',
                'complex' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800',
            ];
        @endphp
        <x-inline-edit-dropdown
            field="complexity"
            :item-id="$task->id"
            :use-parent="true"
            :value="$task->complexity?->value ?? ''"
            dropdown-class="w-48"
            trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full transition-colors cursor-pointer text-xs font-medium"
            :color-map="$complexityColors"
            default-color-class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700"
        >
            <x-slot:trigger>
                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span
                    x-text="{
                        simple: 'Simple',
                        moderate: 'Moderate',
                        complex: 'Complex'
                    }[selectedValue || ''] || 'Complexity'"
                >{{ $task->complexity ? ucfirst($task->complexity->value) : 'Complexity' }}</span>
            </x-slot:trigger>

            <x-slot:options>
                <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    Complexity
                </div>
                <button
                    @click="select('simple')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    :class="selectedValue === 'simple' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                >
                    Simple
                </button>
                <button
                    @click="select('moderate')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    :class="selectedValue === 'moderate' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                >
                    Moderate
                </button>
                <button
                    @click="select('complex')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    :class="selectedValue === 'complex' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                >
                    Complex
                </button>
            </x-slot:options>
        </x-inline-edit-dropdown>

        {{-- Duration --}}
        <x-inline-edit-dropdown
            field="duration"
            :item-id="$task->id"
            :use-parent="true"
            :value="$task->duration"
            dropdown-class="w-48 max-h-60 overflow-y-auto"
            trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-xs font-medium"
        >
            <x-slot:trigger>
                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span
                    x-text="selectedValue ? (() => { const mins = parseInt(selectedValue); if (mins >= 60) { const hours = Math.floor(mins / 60); const remainingMins = mins % 60; if (remainingMins === 0) { return hours + (hours === 1 ? ' hour' : ' hours') + (mins >= 480 ? '+' : ''); } return hours + (hours === 1 ? ' hour' : ' hours') + ' ' + remainingMins + ' min'; } return mins + ' min'; })() : 'No duration'"
                >@php
                    if ($task->duration) {
                        $mins = $task->duration;
                        if ($mins >= 60) {
                            $hours = floor($mins / 60);
                            $remainingMins = $mins % 60;
                            if ($remainingMins === 0) {
                                echo $hours . ($hours === 1 ? ' hour' : ' hours') . ($mins >= 480 ? '+' : '');
                            } else {
                                echo $hours . ($hours === 1 ? ' hour' : ' hours') . ' ' . $remainingMins . ' min';
                            }
                        } else {
                            echo $mins . ' min';
                        }
                    } else {
                        echo 'No duration';
                    }
                @endphp</span>
            </x-slot:trigger>

            <x-slot:options>
                <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    Duration
                </div>
                <button
                    x-show="selectedValue !== null && selectedValue !== ''"
                    @click="select(null)"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 text-red-600 dark:text-red-400"
                >
                    Clear
                </button>
                @foreach([10, 15, 30, 45, 60, 120, 240, 480] as $minutes)
                    @php
                        if ($minutes < 60) {
                            $displayText = $minutes . ($minutes === 1 ? ' minute' : ' minutes');
                        } else {
                            $hours = floor($minutes / 60);
                            $displayText = $hours . ($hours === 1 ? ' hour' : ' hours') . ($minutes === 480 ? '+' : '');
                        }
                    @endphp
                    <button
                        @click="select({{ $minutes }})"
                        class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                        :class="selectedValue === {{ $minutes }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                    >
                        {{ $displayText }}
                    </button>
                @endforeach
            </x-slot:options>
        </x-inline-edit-dropdown>
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

    {{-- Actions Section --}}
    <div class="mt-auto pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700 flex justify-end">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs sm:text-sm font-medium text-purple-700 dark:text-purple-200 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/40 dark:hover:bg-purple-800/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-900 transition-colors"
            wire:click="$dispatch('view-task-detail', { id: {{ $task->id }} })"
            aria-label="View task details: {{ $task->title }}"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span>View details</span>
        </button>
    </div>

</div>

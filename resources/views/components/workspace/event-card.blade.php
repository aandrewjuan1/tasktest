@props(['event'])

<div
    class="@container bg-white dark:bg-zinc-800 rounded-lg rounded-br-lg border border-zinc-200 dark:border-zinc-700 p-2 sm:p-3 flex flex-col h-full"
    wire:click="$dispatch('view-event-detail', { id: {{ $event->id }} })"
    role="button"
    tabindex="0"
    aria-label="View event details: {{ $event->title }}"
>
    {{-- Header Section --}}
    <div class="mb-2">
        {{-- First Row: Title, Status Pills, and Badges --}}
        <div class="flex flex-col @[300px]:flex-row @[300px]:items-center gap-2 sm:gap-3 mb-2">
            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-base sm:text-lg leading-tight flex-1 min-w-0 flex items-center gap-1.5 sm:gap-2 flex-wrap">
                <span class="line-clamp-2">{{ $event->title }}</span>
                @if($event->tags->isNotEmpty())
                    <span class="flex items-center gap-0.5 sm:gap-1 flex-shrink-0" @click.stop>
                        @foreach($event->tags->take(3) as $tag)
                            <span
                                class="inline-flex items-center px-0.5 sm:px-1 py-0 text-[10px] sm:text-xs rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400"
                            >
                                {{ $tag->name }}
                            </span>
                        @endforeach
                        @if($event->tags->count() > 3)
                            <span class="inline-flex items-center px-0.5 sm:px-1 py-0 text-[10px] sm:text-xs rounded bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-500">
                                +{{ $event->tags->count() - 3 }}
                            </span>
                        @endif
                    </span>
                @endif
            </h3>

            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0" @click.stop>
                <span class="inline-flex items-center px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-xs font-medium rounded-md bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                    Event
                </span>
                @php
                    $statusColors = [
                        'scheduled' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600',
                        'ongoing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800',
                        'completed' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800',
                        'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800',
                        'tentative' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-800',
                    ];
                @endphp
                <x-inline-edit-dropdown
                    field="status"
                    :item-id="$event->id"
                    item-type="event"
                    :use-parent="true"
                    :value="$event->status?->value ?? 'scheduled'"
                    :instance-date="($event->is_instance ?? false) && isset($event->instance_date) ? $event->instance_date->format('Y-m-d') : null"
                    dropdown-class="w-48"
                    trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full transition-colors cursor-pointer text-xs font-medium"
                    :color-map="$statusColors"
                    default-color-class="bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600"
                >
                    <x-slot:trigger>
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span
                            x-text="{
                                scheduled: 'Scheduled',
                                ongoing: 'Ongoing',
                                completed: 'Completed',
                                cancelled: 'Cancelled',
                                tentative: 'Tentative',
                            }[selectedValue || 'scheduled']"
                        >{{ match($event->status?->value ?? 'scheduled') {
                            'scheduled' => 'Scheduled',
                            'ongoing' => 'Ongoing',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            'tentative' => 'Tentative',
                        } }}</span>
                    </x-slot:trigger>

                    <x-slot:options>
                        <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                            Status
                        </div>
                        <button
                            @click="select('scheduled')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'scheduled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            Scheduled
                        </button>
                        <button
                            @click="select('ongoing')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'ongoing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            Ongoing
                        </button>
                        <button
                            @click="select('completed')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'completed' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            Completed
                        </button>
                        <button
                            @click="select('cancelled')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'cancelled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            Cancelled
                        </button>
                        <button
                            @click="select('tentative')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === 'tentative' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            Tentative
                        </button>
                    </x-slot:options>
                </x-inline-edit-dropdown>
            </div>
        </div>

        {{-- Second Row: Dates --}}
        <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5 sm:mb-0 flex-wrap" @click.stop>
            <x-workspace.inline-edit-date-picker
                field="startDatetime"
                :item-id="$event->id"
                item-type="event"
                :value="$event->start_datetime?->toIso8601String()"
                label="Start"
                type="datetime-local"
                trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-xs font-medium"
            />

            <x-workspace.inline-edit-date-picker
                field="endDatetime"
                :item-id="$event->id"
                item-type="event"
                :value="$event->end_datetime?->toIso8601String()"
                label="End"
                type="datetime-local"
                trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-xs font-medium"
            />
        </div>
    </div>

    {{-- Badges Section --}}
    <div class="flex flex-wrap gap-1.5 sm:gap-2 mb-3 sm:mb-4" @click.stop>
        {{-- Recurrence --}}
        <x-workspace.inline-edit-recurrence
            :item-id="$event->id"
            item-type="event"
            :recurring-event="$event->recurringEvent"
            trigger-class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/40 transition-colors cursor-pointer text-xs font-medium"
        />
    </div>
</div>

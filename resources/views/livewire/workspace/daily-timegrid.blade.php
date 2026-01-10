<?php

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component
{
    #[Reactive]
    public Collection $items;

    #[Reactive]
    public ?Carbon $currentDate = null;

    #[Reactive]
    public string $viewMode = 'daily-timegrid';

    #[Reactive]
    public ?string $filterType = null;

    #[Reactive]
    public ?string $filterPriority = null;

    #[Reactive]
    public ?string $filterStatus = null;

    #[Reactive]
    public ?string $sortBy = null;

    #[Reactive]
    public string $sortDirection = 'asc';

    #[Reactive]
    public bool $hasActiveFilters = false;

    public int $startHour = 6;

    public int $endHour = 23;

    public int $hourHeight = 60;

    public int $slotIncrement = 15;

    public function mount(
        Collection $items,
        ?Carbon $currentDate = null,
        string $viewMode = 'daily-timegrid',
        ?string $filterType = null,
        ?string $filterPriority = null,
        ?string $filterStatus = null,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        bool $hasActiveFilters = false
    ): void {
        $this->items = $items;
        $this->currentDate = $currentDate ?? now();
        $this->viewMode = $viewMode;
        $this->filterType = $filterType;
        $this->filterPriority = $filterPriority;
        $this->filterStatus = $filterStatus;
        $this->sortBy = $sortBy;
        $this->sortDirection = $sortDirection;
        $this->hasActiveFilters = $hasActiveFilters;
    }

    public function switchToWeekly(): void
    {
        $this->dispatch('switch-to-weekly-timegrid');
    }

    public function previousDay(): void
    {
        if ($this->currentDate) {
            $newDate = $this->currentDate->copy()->subDay();
            $this->dispatch('switch-to-daily-timegrid', date: $newDate->format('Y-m-d'));
        }
    }

    public function nextDay(): void
    {
        if ($this->currentDate) {
            $newDate = $this->currentDate->copy()->addDay();
            $this->dispatch('switch-to-daily-timegrid', date: $newDate->format('Y-m-d'));
        }
    }

    public function goToToday(): void
    {
        $this->dispatch('switch-to-daily-timegrid', date: now()->format('Y-m-d'));
    }

    #[Computed]
    public function dayDays(): array
    {
        return [$this->currentDate ?? now()];
    }

    #[Computed]
    public function timegridItems(): array
    {
        $itemsByDay = [];
        $days = $this->dayDays;

        foreach ($days as $day) {
            $dateKey = $day->format('Y-m-d');
            $itemsByDay[$dateKey] = [
                'timed' => collect(),
            ];
        }

        // Process only tasks for the time grid
        foreach ($this->items as $item) {
            // Only process tasks
            if ($item->item_type !== 'task') {
                continue;
            }

            // Skip tasks without start_datetime
            if (! $item->start_datetime) {
                continue;
            }

            $startDateTime = Carbon::parse($item->start_datetime);
            $startDate = $startDateTime->copy()->startOfDay();

            // Only show tasks on their start date
            foreach ($days as $day) {
                $dayStart = $day->copy()->startOfDay();

                // Check if this day matches the start date
                if ($dayStart->eq($startDate)) {
                    $dateKey = $day->format('Y-m-d');

                    // Only show as timed if it has a time component (not just a date)
                    if ($startDateTime->format('H:i') !== '00:00') {
                        // Snap start time down to nearest 30-minute interval
                        $startMinutes = $startDateTime->minute;
                        $snappedMinutes = floor($startMinutes / 30) * 30;
                        $startDateTime->minute($snappedMinutes)->second(0);

                        // Snap duration up to nearest 30-minute interval
                        $durationMinutes = $item->duration ?? 60;
                        $snappedDuration = ceil($durationMinutes / 30) * 30;
                        $endDateTime = $startDateTime->copy()->addMinutes($snappedDuration);

                        // Calculate position and height
                        $startMinutes = ($startDateTime->hour * 60) + $startDateTime->minute;
                        $endMinutes = ($endDateTime->hour * 60) + $endDateTime->minute;

                        $gridStartMinutes = $this->startHour * 60;
                        $gridEndMinutes = $this->endHour * 60;

                        if ($startMinutes < $gridEndMinutes && $endMinutes > $gridStartMinutes) {
                            $topMinutes = max($startMinutes - $gridStartMinutes, 0);
                            $durationMinutes = min($endMinutes, $gridEndMinutes) - max($startMinutes, $gridStartMinutes);

                            // Clone the item to avoid mutating the reactive prop
                            $itemCopy = clone $item;
                            $itemCopy->grid_top = ($topMinutes / 60) * $this->hourHeight;
                            $itemCopy->grid_height = ($durationMinutes / 60) * $this->hourHeight;
                            $itemCopy->computed_start_datetime = $startDateTime;

                            $itemsByDay[$dateKey]['timed']->push($itemCopy);
                        }
                    }
                }
            }
        }

        return $itemsByDay;
    }

    #[Computed]
    public function filterDescription(): string
    {
        $parts = [];

        if ($this->filterType && $this->filterType !== 'all') {
            $typeLabel = match ($this->filterType) {
                'task' => 'tasks',
                'event' => 'events',
                default => $this->filterType,
            };
            $parts[] = "Showing {$typeLabel} only";
        }

        if ($this->filterPriority && $this->filterPriority !== 'all') {
            $priorityLabel = ucfirst($this->filterPriority);
            $parts[] = "Priority: {$priorityLabel}";
        }

        if ($this->filterStatus && $this->filterStatus !== 'all') {
            $statusLabel = match ($this->filterStatus) {
                'to_do' => 'To Do',
                'doing' => 'In Progress',
                'done' => 'Done',
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'tentative' => 'Tentative',
                default => ucfirst($this->filterStatus),
            };
            $parts[] = "Status: {$statusLabel}";
        }

        return implode(' • ', $parts);
    }

    #[Computed]
    public function sortDescription(): ?string
    {
        if (! $this->sortBy) {
            return null;
        }

        $sortLabel = match ($this->sortBy) {
            'priority' => 'Priority',
            'created_at' => 'Date Created',
            'start_datetime' => 'Start Date',
            'end_datetime' => 'End Date',
            'title' => 'Title/Name',
            'status' => 'Status',
            default => ucfirst(str_replace('_', ' ', $this->sortBy)),
        };

        $direction = $this->sortDirection === 'asc' ? '↑' : '↓';

        return "Sorted by: {$sortLabel} {$direction}";
    }

    #[Computed]
    public function dueDateIndicators(): array
    {
        $indicatorsByDay = [];
        $days = $this->dayDays;

        foreach ($days as $day) {
            $dateKey = $day->format('Y-m-d');
            $indicatorsByDay[$dateKey] = collect();
        }

        // Process all items to find due dates
        foreach ($this->items as $item) {
            // Skip items without end_datetime
            if (! $item->end_datetime) {
                continue;
            }

            $endDateTime = Carbon::parse($item->end_datetime);
            $endDate = $endDateTime->copy()->startOfDay();

            // Check if the due date falls within the active days
            foreach ($days as $day) {
                $dayStart = $day->copy()->startOfDay();
                $dayEnd = $day->copy()->endOfDay();

                // Check if this day matches the due date
                if ($dayStart->lte($endDate) && $dayEnd->gte($endDate)) {
                    $dateKey = $day->format('Y-m-d');

                    // Only show indicator if end_datetime has a time component
                    if ($endDateTime->format('H:i') !== '00:00') {
                        $dueHour = $endDateTime->hour;
                        $dueMinute = $endDateTime->minute;

                        // Calculate position: 1 hour before due time to due time
                        $indicatorStartDateTime = $endDateTime->copy()->subHour();
                        $indicatorStartHour = $indicatorStartDateTime->hour;
                        $indicatorStartMinute = $indicatorStartDateTime->minute;
                        $indicatorStartMinutes = ($indicatorStartHour * 60) + $indicatorStartMinute;
                        $indicatorEndMinutes = ($dueHour * 60) + $dueMinute;

                        $gridStartMinutes = $this->startHour * 60;
                        $gridEndMinutes = $this->endHour * 60;

                        // Only show if indicator falls within visible grid
                        if ($indicatorEndMinutes > $gridStartMinutes && $indicatorStartMinutes < $gridEndMinutes) {
                            $topMinutes = max($indicatorStartMinutes - $gridStartMinutes, 0);
                            $heightMinutes = min($indicatorEndMinutes, $gridEndMinutes) - max($indicatorStartMinutes, $gridStartMinutes);

                            $indicator = (object) [
                                'item_id' => $item->id,
                                'item_type' => $item->item_type,
                                'item_name' => $item->title ?? $item->name,
                                'due_datetime' => $endDateTime,
                                'grid_top' => ($topMinutes / 60) * $this->hourHeight,
                                'grid_height' => ($heightMinutes / 60) * $this->hourHeight,
                            ];

                            $indicatorsByDay[$dateKey]->push($indicator);
                        }
                    }
                }
            }
        }

        return $indicatorsByDay;
    }
}; ?>

<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <!-- View Navigation -->
    <x-workspace.view-navigation
        :view-mode="$viewMode"
        :current-date="$currentDate"
        :filter-type="$filterType"
        :filter-priority="$filterPriority"
        :filter-status="$filterStatus"
        :sort-by="$sortBy"
        :sort-direction="$sortDirection"
        :has-active-filters="$hasActiveFilters"
        :filter-description="$this->filterDescription"
        :sort-description="$this->sortDescription"
        no-wrapper
    />

    <!-- Timegrid Mode Toggle -->
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-center gap-2">
            <div class="bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-700 p-1">
                <div class="flex gap-1" role="group" aria-label="Timegrid mode selection">
                    <flux:tooltip content="Day View">
                        <button
                            aria-label="Day view (current)"
                            aria-pressed="true"
                            class="px-3 py-1.5 rounded transition-colors bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                            disabled
                        >
                            Day
                        </button>
                    </flux:tooltip>
                    <flux:tooltip content="Week View">
                        <button
                            wire:click="switchToWeekly"
                            aria-label="Switch to week view"
                            aria-pressed="false"
                            class="px-3 py-1.5 rounded transition-colors bg-transparent text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600"
                        >
                            Week
                        </button>
                    </flux:tooltip>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Timegrid Calendar Grid -->
    <div class="overflow-x-auto">
        <div class="min-w-[800px]">
            <!-- Day View: Single Column Layout -->
            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
                <div class="w-[10%] p-2 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-r border-zinc-200 dark:border-zinc-700">
                    Time
                </div>
                <div class="w-[90%] p-2 text-center border-r border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                        {{ ($currentDate ?? now())->format('D') }}
                    </div>
                    <div class="text-sm font-bold {{ ($currentDate ?? now())->isToday() ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ ($currentDate ?? now())->format('M j, Y') }}
                    </div>
                </div>
            </div>

            <!-- Time Grid for Day View -->
            <div class="flex relative">
                <!-- Time Column -->
                <div class="w-[10%] border-r border-zinc-200 dark:border-zinc-700">
                    @for($hour = $startHour; $hour <= $endHour; $hour++)
                        <div class="text-xs text-right pr-2 text-zinc-500 dark:text-zinc-400" style="height: {{ $hourHeight }}px; line-height: {{ $hourHeight }}px;">
                            {{ Carbon::createFromTime($hour, 0)->format('g A') }}
                        </div>
                    @endfor
                </div>

                <!-- Day Column -->
                @php
                    $day = $currentDate ?? now();
                    $dateKey = $day->format('Y-m-d');
                    $timedItems = $this->timegridItems[$dateKey]['timed'] ?? collect();
                    $isToday = $day->isToday();
                @endphp
                <div wire:key="day-column-{{ $dateKey }}" class="w-[90%] relative {{ $isToday ? 'bg-blue-50 dark:bg-blue-950/20' : '' }}"
                     role="gridcell"
                     aria-label="{{ $day->format('l, F j') }}"
                >
                    <!-- Hour Lines -->
                    @for($hour = $startHour; $hour <= $endHour; $hour++)
                        <div class="absolute w-full border-t border-zinc-200 dark:border-zinc-700" style="top: {{ ($hour - $startHour) * $hourHeight }}px;"></div>
                    @endfor

                    <!-- 30-Minute Grid Lines -->
                    @for($hour = $startHour; $hour < $endHour; $hour++)
                        <div class="absolute w-full border-t border-dotted border-zinc-100 dark:border-zinc-800" style="top: {{ (($hour - $startHour) * $hourHeight) + ($hourHeight / 2) }}px;"></div>
                    @endfor

                    <!-- Due Date Indicators -->
                    @php
                        $dueIndicators = $this->dueDateIndicators[$dateKey] ?? collect();
                    @endphp
                    @foreach($dueIndicators as $indicator)
                        <div
                            wire:key="due-indicator-{{ $indicator->item_type }}-{{ $indicator->item_id }}"
                            class="absolute w-full bg-red-500 dark:bg-red-600 text-white text-xs px-1 py-0.5 opacity-80 z-0"
                            style="top: {{ $indicator->grid_top }}px; height: {{ $indicator->grid_height }}px;"
                        >
                            <div class="font-semibold truncate">{{ $indicator->item_name }}</div>
                            <div class="text-xs opacity-90">
                                Due: {{ $indicator->due_datetime->format('g:i A') }}
                            </div>
                        </div>
                    @endforeach

                    <!-- Timed Items (Tasks Only) -->
                    @foreach($timedItems as $item)
                        @php
                            $currentDuration = $item->duration ?? 60;
                        @endphp
                        <div
                            wire:key="timed-item-{{ $item->item_type }}-{{ $item->id }}"
                            role="button"
                            tabindex="0"
                            aria-label="{{ $item->title }} from {{ ($item->computed_start_datetime ?? Carbon::parse($item->start_datetime))->format('g:i A') }}"
                            x-data="{
                                isResizing: false,
                                initialY: 0,
                                initialHeight: {{ $item->grid_height }},
                                initialDuration: {{ $currentDuration }},
                                currentHeight: {{ $item->grid_height }},
                                itemTop: {{ $item->grid_top }},
                                hourHeight: {{ $hourHeight }},
                                gridInterval: 30,
                                minHeight: {{ ($hourHeight / 60) * 30 }},
                                startResize(e) {
                                    if (e.target.classList.contains('resize-handle')) {
                                        this.isResizing = true;
                                        this.initialY = e.clientY;
                                        this.initialHeight = this.currentHeight;
                                        e.preventDefault();
                                        e.stopPropagation();

                                        const handleMove = (moveEvent) => {
                                            if (!this.isResizing) return;
                                            const deltaY = moveEvent.clientY - this.initialY;
                                            const newHeight = Math.max(this.minHeight, this.initialHeight + deltaY);
                                            const newDuration = (newHeight / this.hourHeight) * 60;
                                            const snappedDuration = Math.max(30, Math.round(newDuration / this.gridInterval) * this.gridInterval);
                                            const snappedHeight = (snappedDuration / 60) * this.hourHeight;
                                            this.currentHeight = snappedHeight;
                                        };

                                        const handleUp = (upEvent) => {
                                            if (!this.isResizing) return;
                                            const deltaY = upEvent.clientY - this.initialY;
                                            const newHeight = Math.max(this.minHeight, this.initialHeight + deltaY);
                                            const newDuration = (newHeight / this.hourHeight) * 60;
                                            const snappedDuration = Math.max(30, Math.round(newDuration / this.gridInterval) * this.gridInterval);

                                            try {
                                                $dispatch('update-item-duration', {
                                                    itemId: {{ $item->id }},
                                                    itemType: '{{ $item->item_type }}',
                                                    newDurationMinutes: snappedDuration
                                                });
                                            } catch (error) {
                                                console.error('Error updating duration:', error);
                                            }

                                            this.isResizing = false;
                                            this.initialHeight = this.currentHeight;
                                            this.initialDuration = snappedDuration;

                                            document.removeEventListener('mousemove', handleMove);
                                            document.removeEventListener('mouseup', handleUp);
                                        };

                                        document.addEventListener('mousemove', handleMove);
                                        document.addEventListener('mouseup', handleUp);
                                    }
                                }
                            }"
                            @mousedown="startResize($event)"
                            wire:loading.class="opacity-50"
                            wire:target="handleUpdateItemDuration"
                            class="absolute w-full px-1 py-1 text-xs rounded cursor-pointer overflow-hidden hover:z-20 hover:shadow-lg transition-shadow"
                            :style="`top: ${itemTop}px; height: ${isResizing ? currentHeight : {{ $item->grid_height }}}px; background-color: {{ $item->color ?? '#3b82f6' }}; color: white;`"
                            @click="
                                if (!isResizing && !$event.target.classList.contains('resize-handle')) {
                                    $dispatch('view-{{ $item->item_type }}-detail', { id: {{ $item->id }} });
                                }
                            "
                            @keydown.enter.prevent="$dispatch('view-{{ $item->item_type }}-detail', { id: {{ $item->id }} })"
                        >
                            <div class="font-semibold truncate">{{ $item->title }}</div>
                            @if(isset($item->computed_start_datetime))
                                <div class="text-xs opacity-90">
                                    {{ $item->computed_start_datetime->format('g:i A') }}
                                </div>
                            @endif
                            <div
                                class="resize-handle absolute bottom-0 left-0 right-0 bg-white/30 hover:bg-white/50 cursor-ns-resize transition-colors z-10"
                                style="height: 3px;"
                                aria-label="Resize handle"
                                role="slider"
                                tabindex="0"
                            ></div>
                            <!-- Loading indicator -->
                            <div wire:loading wire:target="handleUpdateItemDuration" class="absolute inset-0 flex items-center justify-center bg-white/30 dark:bg-black/30 rounded">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

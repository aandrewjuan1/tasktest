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
    public ?Carbon $weekStartDate = null;

    #[Reactive]
    public string $viewMode = 'weekly';

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
        ?Carbon $weekStartDate = null,
        string $viewMode = 'weekly',
        ?string $filterType = null,
        ?string $filterPriority = null,
        ?string $filterStatus = null,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        bool $hasActiveFilters = false
    ): void {
        $this->items = $items;
        $this->weekStartDate = $weekStartDate ?? now()->startOfWeek();
        $this->viewMode = $viewMode;
        $this->filterType = $filterType;
        $this->filterPriority = $filterPriority;
        $this->filterStatus = $filterStatus;
        $this->sortBy = $sortBy;
        $this->sortDirection = $sortDirection;
        $this->hasActiveFilters = $hasActiveFilters;
    }

    public function previousWeek(): void
    {
        $newWeekStart = $this->weekStartDate->copy()->subWeek();
        $this->dispatch('switch-to-week-view', weekStart: $newWeekStart->format('Y-m-d'));
    }

    public function nextWeek(): void
    {
        $newWeekStart = $this->weekStartDate->copy()->addWeek();
        $this->dispatch('switch-to-week-view', weekStart: $newWeekStart->format('Y-m-d'));
    }

    public function goToToday(): void
    {
        $newWeekStart = now()->startOfWeek();
        $this->dispatch('switch-to-week-view', weekStart: $newWeekStart->format('Y-m-d'));
    }

    #[Computed]
    public function weekDays(): array
    {
        $days = [];
        $current = $this->weekStartDate->copy();

        for ($i = 0; $i < 7; $i++) {
            $days[] = $current->copy();
            $current->addDay();
        }

        return $days;
    }

    #[Computed]
    public function weeklyItems(): array
    {
        $itemsByDay = [];

        foreach ($this->weekDays as $day) {
            $dateKey = $day->format('Y-m-d');
            $itemsByDay[$dateKey] = [
                'all_day' => collect(),
                'timed' => collect(),
            ];
        }

        // Process items from parent
        foreach ($this->items as $item) {
            $dateKey = null;

            if ($item->item_type === 'task') {
                $dateKey = Carbon::parse($item->start_date)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    // Check if task has a start_time
                    if ($item->start_time) {
                        // Combine start_date and start_time to create datetime
                        $startDateString = Carbon::parse($item->start_date)->format('Y-m-d');
                        $startDateTime = Carbon::parse($startDateString . ' ' . $item->start_time);

                        // Calculate end datetime using duration (default to 60 minutes if null)
                        $durationMinutes = $item->duration ?? 60;
                        $endDateTime = $startDateTime->copy()->addMinutes($durationMinutes);

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
                    } else {
                        // No start_time, add to all-day section
                        $itemsByDay[$dateKey]['all_day']->push($item);
                    }
                }
            } elseif ($item->item_type === 'event') {
                $dateKey = Carbon::parse($item->start_datetime)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    // Calculate position and height
                    $startTime = Carbon::parse($item->start_datetime);
                    $endTime = Carbon::parse($item->end_datetime);

                    $startMinutes = ($startTime->hour * 60) + $startTime->minute;
                    $endMinutes = ($endTime->hour * 60) + $endTime->minute;

                    $gridStartMinutes = $this->startHour * 60;
                    $gridEndMinutes = $this->endHour * 60;

                    if ($startMinutes < $gridEndMinutes && $endMinutes > $gridStartMinutes) {
                        $topMinutes = max($startMinutes - $gridStartMinutes, 0);
                        $durationMinutes = min($endMinutes, $gridEndMinutes) - max($startMinutes, $gridStartMinutes);

                        // Clone the item to avoid mutating the reactive prop
                        $itemCopy = clone $item;
                        $itemCopy->grid_top = ($topMinutes / 60) * $this->hourHeight;
                        $itemCopy->grid_height = ($durationMinutes / 60) * $this->hourHeight;

                        $itemsByDay[$dateKey]['timed']->push($itemCopy);
                    }
                }
            } elseif ($item->item_type === 'project') {
                $dateKey = Carbon::parse($item->start_date)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    $itemsByDay[$dateKey]['all_day']->push($item);
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
            $typeLabel = match($this->filterType) {
                'task' => 'tasks',
                'event' => 'events',
                'project' => 'projects',
                default => $this->filterType,
            };
            $parts[] = "Showing {$typeLabel} only";
        }

        if ($this->filterPriority && $this->filterPriority !== 'all') {
            $priorityLabel = ucfirst($this->filterPriority);
            $parts[] = "Priority: {$priorityLabel}";
        }

        if ($this->filterStatus && $this->filterStatus !== 'all') {
            $statusLabel = match($this->filterStatus) {
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
        if (!$this->sortBy) {
            return null;
        }

        $sortLabel = match($this->sortBy) {
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
}; ?>

<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <!-- View Navigation -->
    <x-workspace.view-navigation
        :view-mode="$viewMode"
        :week-start-date="$weekStartDate"
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

    <!-- Weekly Calendar Grid -->
    <div class="overflow-x-auto">
        <div class="min-w-[800px]">
            <!-- Day Headers -->
            <div class="grid grid-cols-8 border-b border-zinc-200 dark:border-zinc-700">
                <div class="p-2 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-r border-zinc-200 dark:border-zinc-700">
                    Time
                </div>
                @foreach($this->weekDays as $day)
                    <div wire:key="day-header-{{ $day->format('Y-m-d') }}" class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700">
                        <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ $day->format('D') }}
                        </div>
                        <div class="text-sm font-bold {{ $day->isToday() ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                            {{ $day->format('j') }}
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- All-Day Section -->
            <div class="grid grid-cols-8 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50">
                <div class="p-2 text-xs font-medium text-zinc-500 dark:text-zinc-400 border-r border-zinc-200 dark:border-zinc-700">
                    All Day
                </div>
                @foreach($this->weekDays as $day)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $allDayItems = $this->weeklyItems[$dateKey]['all_day'] ?? collect();
                    @endphp
                    <div wire:key="all-day-column-{{ $dateKey }}" class="p-1 min-h-[60px] border-r border-zinc-200 dark:border-zinc-700">
                        @foreach($allDayItems as $item)
                            <div wire:key="all-day-item-{{ $item->item_type }}-{{ $item->id }}"
                                 class="mb-1 text-xs p-1 rounded cursor-pointer {{ $item->item_type === 'task' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : ($item->item_type === 'event' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200') }}"
                                 wire:click="$dispatch('view-{{ $item->item_type }}-detail', { id: {{ $item->id }} })">
                                {{ Str::limit($item->title ?? $item->name, 15) }}
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <!-- Time Grid -->
            <div class="grid grid-cols-8 relative">
                <!-- Time Column -->
                <div class="border-r border-zinc-200 dark:border-zinc-700">
                    @for($hour = $startHour; $hour <= $endHour; $hour++)
                        <div class="text-xs text-right pr-2 text-zinc-500 dark:text-zinc-400" style="height: {{ $hourHeight }}px; line-height: {{ $hourHeight }}px;">
                            {{ Carbon::createFromTime($hour, 0)->format('g A') }}
                        </div>
                    @endfor
                </div>

                <!-- Day Columns -->
                @foreach($this->weekDays as $day)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $timedItems = $this->weeklyItems[$dateKey]['timed'] ?? collect();
                        $isToday = $day->isToday();
                    @endphp
                    <div wire:key="day-column-{{ $dateKey }}" class="relative border-r border-zinc-200 dark:border-zinc-700 {{ $isToday ? 'bg-blue-50 dark:bg-blue-950/20' : '' }}"
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

                        <!-- Timed Items -->
                        @foreach($timedItems as $item)
                            @php
                                $currentDuration = $item->item_type === 'event'
                                    ? Carbon::parse($item->end_datetime)->diffInMinutes(Carbon::parse($item->start_datetime))
                                    : ($item->duration ?? 60);
                            @endphp
                            <div
                                wire:key="timed-item-{{ $item->item_type }}-{{ $item->id }}"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $item->title }} from {{ Carbon::parse($item->item_type === 'event' ? $item->start_datetime : ($item->computed_start_datetime ?? $item->start_date))->format('g:i A') }}"
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
                                :style="`top: ${itemTop}px; height: ${isResizing ? currentHeight : {{ $item->grid_height }}}px; background-color: {{ $item->color ?? ($item->item_type === 'event' ? '#8b5cf6' : '#3b82f6') }}; color: white;`"
                                @click="
                                    if (!isResizing && !$event.target.classList.contains('resize-handle')) {
                                        $dispatch('view-{{ $item->item_type }}-detail', { id: {{ $item->id }} });
                                    }
                                "
                                @keydown.enter.prevent="$dispatch('view-{{ $item->item_type }}-detail', { id: {{ $item->id }} })"
                            >
                                <div class="font-semibold truncate">{{ $item->title }}</div>
                                @if($item->item_type === 'event')
                                    <div class="text-xs opacity-90">
                                        {{ Carbon::parse($item->start_datetime)->format('g:i A') }}
                                    </div>
                                @elseif($item->item_type === 'task' && isset($item->computed_start_datetime))
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
                @endforeach
            </div>
        </div>
    </div>
</div>

<?php

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component
{
    #[Reactive]
    public Collection $items;

    #[Reactive]
    public ?Carbon $weekStartDate = null;

    public int $startHour = 6;

    public int $endHour = 23;

    public int $hourHeight = 60;

    public int $slotIncrement = 15;

    public function mount(Collection $items, ?Carbon $weekStartDate = null): void
    {
        $this->items = $items;
        $this->weekStartDate = $weekStartDate ?? now()->startOfWeek();
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

    #[On('switch-to-week-view')]
    public function switchToWeekView(string $weekStart): void
    {
        $this->weekStartDate = Carbon::parse($weekStart);
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

                            $item->grid_top = ($topMinutes / 60) * $this->hourHeight;
                            $item->grid_height = ($durationMinutes / 60) * $this->hourHeight;

                            // Store computed datetime for display purposes
                            $item->computed_start_datetime = $startDateTime;

                            $itemsByDay[$dateKey]['timed']->push($item);
                        }
                    } else {
                        // No start_time, add to all-day section
                        $itemsByDay[$dateKey]['all_day']->push($item);
                    }
                }
            } elseif ($item->item_type === 'event') {
                $dateKey = Carbon::parse($item->start_datetime)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    if ($item->all_day) {
                        $itemsByDay[$dateKey]['all_day']->push($item);
                    } else {
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

                            $item->grid_top = ($topMinutes / 60) * $this->hourHeight;
                            $item->grid_height = ($durationMinutes / 60) * $this->hourHeight;

                            $itemsByDay[$dateKey]['timed']->push($item);
                        }
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
}; ?>

<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <!-- Week Navigation -->
    <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                {{ $weekStartDate->format('M d') }} - {{ $weekStartDate->copy()->addDays(6)->format('M d, Y') }}
            </h3>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" size="sm" wire:click="goToToday">
                Today
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="chevron-left" wire:click="previousWeek">
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="chevron-right" wire:click="nextWeek">
            </flux:button>
        </div>
    </div>

    <!-- Weekly Calendar Grid -->
    <div class="overflow-x-auto">
        <div class="min-w-[800px]">
            <!-- Day Headers -->
            <div class="grid grid-cols-8 border-b border-zinc-200 dark:border-zinc-700">
                <div class="p-2 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-r border-zinc-200 dark:border-zinc-700">
                    Time
                </div>
                @foreach($this->weekDays as $day)
                    <div class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700">
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
                    <div class="p-1 min-h-[60px] border-r border-zinc-200 dark:border-zinc-700">
                        @foreach($allDayItems as $item)
                            <div class="mb-1 text-xs p-1 rounded cursor-pointer {{ $item->item_type === 'task' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : ($item->item_type === 'event' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200') }}"
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
                    <div class="relative border-r border-zinc-200 dark:border-zinc-700 {{ $isToday ? 'bg-blue-50 dark:bg-blue-950/20' : '' }}"
                         x-data="{ draggingOver: false }"
                         @dragover.prevent="draggingOver = true"
                         @dragleave="draggingOver = false"
                         @drop.prevent="
                             draggingOver = false;
                             const itemId = $event.dataTransfer.getData('itemId');
                             const itemType = $event.dataTransfer.getData('itemType');
                             const duration = parseInt($event.dataTransfer.getData('duration') || '60');

                             const rect = $event.currentTarget.getBoundingClientRect();
                             const y = $event.clientY - rect.top;
                             const minutesFromStart = (y / {{ $hourHeight }}) * 60;
                             const totalMinutes = ({{ $startHour }} * 60) + minutesFromStart;
                             const hours = Math.floor(totalMinutes / 60);
                             const minutes = Math.floor((totalMinutes % 60) / 30) * 30;

                             const newStart = '{{ $dateKey }} ' + String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':00';
                             const endDate = new Date('{{ $dateKey }} ' + String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0'));
                             endDate.setMinutes(endDate.getMinutes() + duration);
                             const newEnd = endDate.toISOString().slice(0, 19).replace('T', ' ');

                             $dispatch('update-item-datetime', {
                                 itemId: parseInt(itemId),
                                 itemType: itemType,
                                 newStart: newStart,
                                 newEnd: newEnd
                             });
                         "
                         :class="{ 'ring-2 ring-blue-500': draggingOver }"
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

                                                $dispatch('update-item-duration', {
                                                    itemId: {{ $item->id }},
                                                    itemType: '{{ $item->item_type }}',
                                                    newDurationMinutes: snappedDuration
                                                });

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
                                draggable="true"
                                @dragstart="
                                    if (!isResizing && !$event.target.classList.contains('resize-handle')) {
                                        $event.dataTransfer.effectAllowed = 'move';
                                        $event.dataTransfer.setData('itemId', {{ $item->id }});
                                        $event.dataTransfer.setData('itemType', '{{ $item->item_type }}');
                                        $event.dataTransfer.setData('duration', {{ $currentDuration }});
                                    } else {
                                        $event.preventDefault();
                                    }
                                "
                                class="absolute w-full px-1 py-1 text-xs rounded cursor-move overflow-hidden hover:z-20 hover:shadow-lg transition-shadow"
                                :style="`top: ${itemTop}px; height: ${isResizing ? currentHeight : {{ $item->grid_height }}}px; background-color: {{ $item->color ?? ($item->item_type === 'event' ? '#8b5cf6' : '#3b82f6') }}; color: white;`"
                                @click="
                                    if (!isResizing && !$event.target.classList.contains('resize-handle')) {
                                        $dispatch('view-{{ $item->item_type }}-detail', { id: {{ $item->id }} });
                                    }
                                "
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
                                ></div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

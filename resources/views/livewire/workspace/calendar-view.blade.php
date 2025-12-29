<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Event;
use App\Models\Task;
use Carbon\Carbon;

new class extends Component {
    public int $year;
    public int $month;
    public ?Carbon $focusedDate = null;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->focusedDate = now();
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function goToToday(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->focusedDate = now();
        $this->dispatch('date-focused', date: now()->format('Y-m-d'));
    }

    #[On('date-focused')]
    public function updateFocusedDate(string $date): void
    {
        $this->focusedDate = Carbon::parse($date);
    }

    #[Computed]
    public function currentDate(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1);
    }

    #[Computed]
    public function monthName(): string
    {
        return $this->currentDate->format('F Y');
    }

    #[Computed]
    public function calendarDays(): array
    {
        $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
        $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();

        $days = [];
        $current = $start->copy();

        while ($current <= $end) {
            $days[] = [
                'date' => $current->copy(),
                'isCurrentMonth' => $current->month === $this->month,
                'isToday' => $current->isToday(),
            ];
            $current->addDay();
        }

        return $days;
    }

    #[Computed]
    public function events(): array
    {
        $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
        $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();

        $events = Event::query()
            ->where('user_id', auth()->id())
            ->with(['tags'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_datetime', [$start, $end])
                    ->orWhereBetween('end_datetime', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_datetime', '<=', $start)
                            ->where('end_datetime', '>=', $end);
                    });
            })
            ->get();

        $eventsByDate = [];
        foreach ($events as $event) {
            $dateKey = $event->start_datetime->format('Y-m-d');
            if (!isset($eventsByDate[$dateKey])) {
                $eventsByDate[$dateKey] = [];
            }
            $eventsByDate[$dateKey][] = $event;
        }

        return $eventsByDate;
    }

    #[Computed]
    public function tasks(): array
    {
        $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
        $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();

        $tasks = Task::query()
            ->where('user_id', auth()->id())
            ->with(['project', 'tags', 'event'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();

        $tasksByDate = [];
        foreach ($tasks as $task) {
            $dateKey = $task->start_date->format('Y-m-d');
            if (!isset($tasksByDate[$dateKey])) {
                $tasksByDate[$dateKey] = [];
            }
            $tasksByDate[$dateKey][] = $task;
        }

        return $tasksByDate;
    }

    public function getItemCountForDate(Carbon $date): int
    {
        $dateKey = $date->format('Y-m-d');
        $eventCount = count($this->events[$dateKey] ?? []);
        $taskCount = count($this->tasks[$dateKey] ?? []);
        return $eventCount + $taskCount;
    }

    public function getUrgencyLevelForDate(Carbon $date): string
    {
        $dateKey = $date->format('Y-m-d');
        $dayTasks = $this->tasks[$dateKey] ?? [];

        foreach ($dayTasks as $task) {
            if ($task->priority?->value === 'urgent') {
                return 'urgent';
            }
        }

        foreach ($dayTasks as $task) {
            if ($task->priority?->value === 'high') {
                return 'high';
            }
        }

        return 'normal';
    }
}; ?>

<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 h-full flex flex-col">
    <!-- Calendar Header -->
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
            {{ $this->monthName }}
        </h2>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" size="sm" wire:click="goToToday">
                Today
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="chevron-left" wire:click="previousMonth">
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="chevron-right" wire:click="nextMonth">
            </flux:button>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="flex-1 overflow-auto">
        <!-- Day Headers -->
        <div class="grid grid-cols-7 gap-1 mb-2">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <div class="text-center text-xs font-semibold text-zinc-600 dark:text-zinc-400 py-2">
                    {{ $day }}
                </div>
            @endforeach
        </div>

        <!-- Calendar Days -->
        <div class="grid grid-cols-7 gap-1">
            @foreach($this->calendarDays as $day)
                @php
                    $itemCount = $this->getItemCountForDate($day['date']);
                    $urgencyLevel = $this->getUrgencyLevelForDate($day['date']);
                    $dayDateString = $day['date']->format('Y-m-d');
                    $isFocused = $this->focusedDate && $this->focusedDate->format('Y-m-d') === $dayDateString;

                    $badgeClasses = match($urgencyLevel) {
                        'urgent' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                        'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                        default => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                    };

                    $ringClasses = '';
                    if ($isFocused) {
                        $ringClasses = 'ring-2 ring-green-500';
                    } elseif ($day['isToday']) {
                        $ringClasses = 'ring-2 ring-blue-500';
                    }
                @endphp
                <div
                    class="min-h-[80px] border rounded-lg p-1 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors {{ $day['isCurrentMonth'] ? 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700' : 'bg-zinc-50 dark:bg-zinc-800/50 border-zinc-100 dark:border-zinc-800' }} {{ $ringClasses }}"
                    @click="$dispatch('switch-to-day-view', { date: '{{ $dayDateString }}' })"
                >
                    <div class="text-xs {{ $day['isCurrentMonth'] ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-600' }} {{ ($day['isToday'] || $isFocused) ? 'font-bold ' : '' }}{{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : ($isFocused ? 'text-green-600 dark:text-green-400' : '') }} mb-1">
                        {{ $day['date']->day }}
                    </div>

                    @if($itemCount > 0)
                        <div class="flex items-center justify-center mt-1">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold {{ $badgeClasses }}">
                                {{ $itemCount }}
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<?php

use App\Models\Event;
use App\Models\Task;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
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
        // Only update if the date is different (prevents unnecessary updates from calendar clicks)
        $parsedDate = Carbon::parse($date);
        if (! $this->focusedDate || $this->focusedDate->format('Y-m-d') !== $parsedDate->format('Y-m-d')) {
            $this->focusedDate = $parsedDate;

            // Update month/year if the focused date is outside the current month view
            if ($parsedDate->month !== $this->month || $parsedDate->year !== $this->year) {
                $this->year = $parsedDate->year;
                $this->month = $parsedDate->month;
            }
        }
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
        $start = $this->currentDate->copy()->startOfMonth();
        $end = $this->currentDate->copy()->endOfMonth();

        $days = [];

        // Add empty cells for days before the first day of the month
        $firstDayOfWeek = $start->dayOfWeek; // 0 = Sunday, 6 = Saturday
        for ($i = 0; $i < $firstDayOfWeek; $i++) {
            $days[] = [
                'date' => null,
                'isCurrentMonth' => false,
                'isToday' => false,
            ];
        }

        // Add all days of the current month
        $current = $start->copy();
        while ($current <= $end) {
            $days[] = [
                'date' => $current->copy(),
                'isCurrentMonth' => true,
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
            ->accessibleBy(auth()->user())
            ->with(['tags'])
            ->whereNotNull('start_datetime')
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
            if (! $event->start_datetime) {
                continue;
            }

            $dateKey = $event->start_datetime->format('Y-m-d');
            if (! isset($eventsByDate[$dateKey])) {
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
            ->accessibleBy(auth()->user())
            ->with(['project', 'tags', 'event'])
            ->whereNotNull('start_datetime')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_datetime', [$start, $end])
                    ->orWhereBetween('end_datetime', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_datetime', '<=', $start)
                            ->where('end_datetime', '>=', $end);
                    });
            })
            ->get();

        $tasksByDate = [];
        foreach ($tasks as $task) {
            if (! $task->start_datetime) {
                continue;
            }

            $dateKey = $task->start_datetime->format('Y-m-d');
            if (! isset($tasksByDate[$dateKey])) {
                $tasksByDate[$dateKey] = [];
            }
            $tasksByDate[$dateKey][] = $task;
        }

        return $tasksByDate;
    }

    #[Computed]
    public function tasksByDueDate(): array
    {
        $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
        $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();

        $tasks = Task::query()
            ->accessibleBy(auth()->user())
            ->with(['project', 'tags', 'event'])
            ->whereNotNull('end_datetime')
            ->whereBetween('end_datetime', [$start, $end])
            ->get();

        $tasksByDueDate = [];
        foreach ($tasks as $task) {
            if (! $task->end_datetime) {
                continue;
            }

            $dateKey = $task->end_datetime->format('Y-m-d');
            if (! isset($tasksByDueDate[$dateKey])) {
                $tasksByDueDate[$dateKey] = [];
            }
            $tasksByDueDate[$dateKey][] = $task;
        }

        return $tasksByDueDate;
    }

    public function getItemCountForDate(?Carbon $date): int
    {
        if (! $date) {
            return 0;
        }
        $dateKey = $date->format('Y-m-d');
        return count($this->tasksByDueDate[$dateKey] ?? []);
    }

    public function getUrgencyLevelForDate(?Carbon $date): string
    {
        if (! $date) {
            return 'low';
        }
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

        foreach ($dayTasks as $task) {
            if ($task->priority?->value === 'medium') {
                return 'medium';
            }
        }

        return 'low';
    }
}; ?>

<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex flex-col sticky top-6" x-cloak>
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
    <div>
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
                @if($day['date'] === null)
                    {{-- Empty cell for days before the first day of the month --}}
                    <div class="min-h-[80px] rounded-lg p-1 border border-transparent"></div>
                @else
                    @php
                        $itemCount = $this->getItemCountForDate($day['date']);
                        $urgencyLevel = $this->getUrgencyLevelForDate($day['date']);
                        $dayDateString = $day['date']->format('Y-m-d');
                        $isFocused = $this->focusedDate && $this->focusedDate->format('Y-m-d') === $dayDateString;

                        $badgeClasses = match($urgencyLevel) {
                            'urgent' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                            'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                            'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                            default => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                        };

                        $borderClasses = '';
                        if ($day['isToday']) {
                            $borderClasses = 'border-2 border-green-500';
                        } elseif ($isFocused) {
                            $borderClasses = 'border-2 border-blue-500';
                        }
                    @endphp
                    <div
                        class="min-h-[80px] rounded-lg p-1 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors bg-white dark:bg-zinc-900 {{ $borderClasses ?: 'border border-zinc-200 dark:border-zinc-700' }}"
                        @click="
                            $wire.$set('focusedDate', '{{ $dayDateString }}');
                            $dispatch('date-focused', { date: '{{ $dayDateString }}' });
                        "
                    >
                        <div class="text-xs text-zinc-900 dark:text-zinc-100 {{ ($day['isToday'] || $isFocused) ? 'font-bold ' : '' }}{{ $day['isToday'] ? 'text-green-600 dark:text-green-400' : ($isFocused ? 'text-blue-600 dark:text-blue-400' : '') }} mb-1">
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
                @endif
            @endforeach
        </div>
    </div>
</div>

<?php

use App\Enums\EventStatus;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // View and display options
    #[Url(except: 'list')]
    public string $viewMode = 'list'; // list, kanban, weekly_calendar

    #[Url(except: 'all')]
    public string $itemType = 'all';

    #[Url(except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    // Weekly calendar view properties
    public ?Carbon $weekStartDate = null;

    // Date navigation for list and kanban views
    public ?Carbon $currentDate = null;

    public int $startHour = 6;

    public int $endHour = 23;

    public int $hourHeight = 60;

    public int $slotIncrement = 15;

    public function mount(): void
    {
        $this->weekStartDate = now()->startOfWeek();
        $this->currentDate = now();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    public function updatingItemType(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->dispatch('open-create-modal');
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function previousWeek(): void
    {
        $this->weekStartDate = $this->weekStartDate->copy()->subWeek();
    }

    public function nextWeek(): void
    {
        $this->weekStartDate = $this->weekStartDate->copy()->addWeek();
    }

    public function goToToday(): void
    {
        $this->weekStartDate = now()->startOfWeek();
    }


    public function goToTodayDate(): void
    {
        $this->currentDate = now();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    public function previousDay(): void
    {
        $this->currentDate = $this->currentDate->copy()->subDay();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    public function nextDay(): void
    {
        $this->currentDate = $this->currentDate->copy()->addDay();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    #[On('switch-to-day-view')]
    public function switchToDayView(string $date): void
    {
        $this->viewMode = 'list';
        $this->currentDate = Carbon::parse($date);
        $this->dispatch('date-focused', date: $date);
    }

    #[On('date-focused')]
    public function updateCurrentDate(string $date): void
    {
        // Only update currentDate if we're in list or kanban view
        if (in_array($this->viewMode, ['list', 'kanban'])) {
            $this->currentDate = Carbon::parse($date);
        }
    }

    #[On('switch-to-week-view')]
    public function switchToWeekView(string $weekStart): void
    {
        $this->viewMode = 'weekly';
        $this->weekStartDate = Carbon::parse($weekStart);
    }


    public function updateItemDateTime(int $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        $model = match ($itemType) {
            'task' => Task::where('id', $itemId)->where('user_id', auth()->id())->first(),
            'event' => Event::where('id', $itemId)->where('user_id', auth()->id())->first(),
            'project' => Project::where('id', $itemId)->where('user_id', auth()->id())->first(),
            default => null,
        };

        if (! $model) {
            return;
        }

        if ($itemType === 'task') {
            $startDateTime = Carbon::parse($newStart);
            $model->start_date = $startDateTime->toDateString();
            $model->start_time = $startDateTime->format('H:i:s');

            if ($newEnd) {
                $model->end_date = Carbon::parse($newEnd)->toDateString();
                // Calculate duration from start and end times
                $endDateTime = Carbon::parse($newEnd);
                $model->duration = $startDateTime->diffInMinutes($endDateTime);
            }
        } elseif ($itemType === 'event') {
            $model->start_datetime = Carbon::parse($newStart);
            if ($newEnd) {
                $model->end_datetime = Carbon::parse($newEnd);
            }
        } elseif ($itemType === 'project') {
            $model->start_date = Carbon::parse($newStart)->toDateString();
            if ($newEnd) {
                $model->end_date = Carbon::parse($newEnd)->toDateString();
            }
        }

        $model->save();
        $this->dispatch('item-updated');
    }

    public function updateItemDuration(int $itemId, string $itemType, int $newDurationMinutes): void
    {
        // Enforce minimum duration of 30 minutes
        $newDurationMinutes = max(30, $newDurationMinutes);

        // Snap to 30-minute grid intervals
        $newDurationMinutes = round($newDurationMinutes / 30) * 30;
        $newDurationMinutes = max(30, $newDurationMinutes); // Ensure still at least 30 after snapping

        $model = match ($itemType) {
            'task' => Task::where('id', $itemId)->where('user_id', auth()->id())->first(),
            'event' => Event::where('id', $itemId)->where('user_id', auth()->id())->first(),
            default => null,
        };

        if (! $model) {
            return;
        }

        if ($itemType === 'task') {
            // For tasks, update duration and recalculate end_date if needed
            $model->duration = $newDurationMinutes;

            if ($model->start_date && $model->start_time) {
                $startDateString = Carbon::parse($model->start_date)->format('Y-m-d');
                $startDateTime = Carbon::parse($startDateString . ' ' . $model->start_time);
                $endDateTime = $startDateTime->copy()->addMinutes($newDurationMinutes);
                $model->end_date = $endDateTime->toDateString();
            }
        } elseif ($itemType === 'event') {
            // For events, update end_datetime while keeping start_datetime
            $startDateTime = Carbon::parse($model->start_datetime);
            $model->end_datetime = $startDateTime->copy()->addMinutes($newDurationMinutes);
        }

        $model->save();
        $this->dispatch('item-updated');
    }

    public function updateItemStatus(int $itemId, string $itemType, string $newStatus): void
    {
        // Prevent events from being dropped in 'doing' column
        if ($itemType === 'event' && $newStatus === 'doing') {
            return;
        }

        $model = match ($itemType) {
            'task' => Task::where('id', $itemId)->where('user_id', auth()->id())->first(),
            'event' => Event::where('id', $itemId)->where('user_id', auth()->id())->first(),
            'project' => Project::where('id', $itemId)->where('user_id', auth()->id())->first(),
            default => null,
        };

        if (! $model) {
            return;
        }

        // Map kanban column status to appropriate enum value for each item type
        if ($itemType === 'task') {
            $statusEnum = match ($newStatus) {
                'to_do' => TaskStatus::ToDo,
                'doing' => TaskStatus::Doing,
                'done' => TaskStatus::Done,
                default => null,
            };

            if ($statusEnum) {
                $model->update(['status' => $statusEnum]);
                $this->dispatch('task-updated');
            }
        } elseif ($itemType === 'event') {
            $statusEnum = match ($newStatus) {
                'to_do' => EventStatus::Scheduled,
                'done' => EventStatus::Completed,
                default => null,
            };

            if ($statusEnum) {
                $model->update(['status' => $statusEnum]);
                $this->dispatch('event-updated');
            }
        }
        // Projects don't have status, so no update needed
    }

    #[Computed]
    public function items(): Collection
    {
        $user = auth()->user();
        $items = collect();

        // Determine if we need to filter by date (for list and kanban views)
        $filterByDate = in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate;
        $selectedDate = $filterByDate ? $this->currentDate->copy()->startOfDay() : null;
        $selectedDateEnd = $filterByDate ? $this->currentDate->copy()->endOfDay() : null;

        // Build queries based on item type filter
        if (in_array($this->itemType, ['all', 'tasks'])) {
            $taskQuery = Task::query()
                ->where('user_id', $user->id)
                ->with(['project', 'tags', 'event']);

            // Filter by date for list/kanban views
            if ($filterByDate) {
                $taskQuery->where(function ($query) use ($selectedDate, $selectedDateEnd) {
                    $query->whereDate('start_date', $selectedDate->toDateString())
                        ->orWhereDate('end_date', $selectedDate->toDateString())
                        ->orWhere(function ($q) use ($selectedDate, $selectedDateEnd) {
                            $q->where('start_date', '<=', $selectedDateEnd)
                                ->where('end_date', '>=', $selectedDate);
                        });
                });
            }

            $tasks = $taskQuery->get();

            $tasks = $tasks->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_date ?? $task->created_at;

                return $task;
            });

            $items = $items->merge($tasks);
        }

        if (in_array($this->itemType, ['all', 'events'])) {
            $eventQuery = Event::query()
                ->where('user_id', $user->id)
                ->with(['tags']);

            // Filter by date for list/kanban views
            if ($filterByDate) {
                $eventQuery->where(function ($query) use ($selectedDate, $selectedDateEnd) {
                    $query->whereDate('start_datetime', $selectedDate->toDateString())
                        ->orWhereDate('end_datetime', $selectedDate->toDateString())
                        ->orWhere(function ($q) use ($selectedDate, $selectedDateEnd) {
                            $q->where('start_datetime', '<=', $selectedDateEnd)
                                ->where('end_datetime', '>=', $selectedDate);
                        });
                });
            }

            $events = $eventQuery->get();

            $events = $events->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime;

                return $event;
            });

            $items = $items->merge($events);
        }

        if (in_array($this->itemType, ['all', 'projects'])) {
            $projectQuery = Project::query()
                ->where('user_id', $user->id)
                ->with(['tags', 'tasks']);

            // Filter by date for list/kanban views
            if ($filterByDate) {
                $projectQuery->where(function ($query) use ($selectedDate, $selectedDateEnd) {
                    $query->whereDate('start_date', $selectedDate->toDateString())
                        ->orWhereDate('end_date', $selectedDate->toDateString())
                        ->orWhere(function ($q) use ($selectedDate, $selectedDateEnd) {
                            $q->where('start_date', '<=', $selectedDateEnd)
                                ->where('end_date', '>=', $selectedDate);
                        });
                });
            }

            $projects = $projectQuery->get();

            $projects = $projects->map(function ($project) {
                $project->item_type = 'project';
                $project->sort_date = $project->created_at;

                return $project;
            });

            $items = $items->merge($projects);
        }

        // Sort items
        $items = $items->sortBy(function ($item) {
            return match ($this->sortBy) {
                'due_date' => $item->sort_date,
                'title' => $item->title ?? $item->name ?? '',
                'priority' => $item->priority?->value ?? 'zzz',
                default => $item->created_at,
            };
        }, SORT_REGULAR, $this->sortDirection === 'desc');

        return $items;
    }

    #[Computed]
    public function availableTags(): Collection
    {
        $userId = auth()->id();

        return Tag::whereHas('tasks', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->orWhereHas('events', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orWhereHas('projects', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableProjects(): Collection
    {
        return Project::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function itemsByStatus(): array
    {
        $items = $this->items;

        return [
            'to_do' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['to_do', 'scheduled', 'tentative'])),
            'doing' => $items->filter(fn ($item) => $item->status?->value === 'doing'),
            'done' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['done', 'completed'])),
        ];
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
        $user = auth()->user();
        $weekStart = $this->weekStartDate->copy()->startOfDay();
        $weekEnd = $this->weekStartDate->copy()->addDays(6)->endOfDay();

        $itemsByDay = [];

        foreach ($this->weekDays as $day) {
            $dateKey = $day->format('Y-m-d');
            $itemsByDay[$dateKey] = [
                'all_day' => collect(),
                'timed' => collect(),
            ];
        }

        // Get all items for the week
        if (in_array($this->itemType, ['all', 'tasks'])) {
            $tasks = Task::query()
                ->where('user_id', $user->id)
                ->with(['project', 'tags', 'event'])
                ->where(function ($query) use ($weekStart, $weekEnd) {
                    $query->whereBetween('start_date', [$weekStart, $weekEnd])
                        ->orWhereBetween('end_date', [$weekStart, $weekEnd]);
                })
                ->get();

            foreach ($tasks as $task) {
                $task->item_type = 'task';
                $dateKey = Carbon::parse($task->start_date)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    // Check if task has a start_time
                    if ($task->start_time) {
                        // Combine start_date and start_time to create datetime
                        $startDateString = Carbon::parse($task->start_date)->format('Y-m-d');
                        $startDateTime = Carbon::parse($startDateString . ' ' . $task->start_time);

                        // Calculate end datetime using duration (default to 60 minutes if null)
                        $durationMinutes = $task->duration ?? 60;
                        $endDateTime = $startDateTime->copy()->addMinutes($durationMinutes);

                        // Calculate position and height (same logic as events)
                        $startMinutes = ($startDateTime->hour * 60) + $startDateTime->minute;
                        $endMinutes = ($endDateTime->hour * 60) + $endDateTime->minute;

                        $gridStartMinutes = $this->startHour * 60;
                        $gridEndMinutes = $this->endHour * 60;

                        if ($startMinutes < $gridEndMinutes && $endMinutes > $gridStartMinutes) {
                            $topMinutes = max($startMinutes - $gridStartMinutes, 0);
                            $durationMinutes = min($endMinutes, $gridEndMinutes) - max($startMinutes, $gridStartMinutes);

                            $task->grid_top = ($topMinutes / 60) * $this->hourHeight;
                            $task->grid_height = ($durationMinutes / 60) * $this->hourHeight;

                            // Store computed datetime for display purposes
                            $task->computed_start_datetime = $startDateTime;

                            $itemsByDay[$dateKey]['timed']->push($task);
                        }
                    } else {
                        // No start_time, add to all-day section
                        $itemsByDay[$dateKey]['all_day']->push($task);
                    }
                }
            }
        }

        if (in_array($this->itemType, ['all', 'events'])) {
            $events = Event::query()
                ->where('user_id', $user->id)
                ->with(['tags'])
                ->where(function ($query) use ($weekStart, $weekEnd) {
                    $query->whereBetween('start_datetime', [$weekStart, $weekEnd])
                        ->orWhereBetween('end_datetime', [$weekStart, $weekEnd]);
                })
                ->get();

            foreach ($events as $event) {
                $event->item_type = 'event';
                $dateKey = Carbon::parse($event->start_datetime)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    if ($event->all_day) {
                        $itemsByDay[$dateKey]['all_day']->push($event);
                    } else {
                        // Calculate position and height
                        $startTime = Carbon::parse($event->start_datetime);
                        $endTime = Carbon::parse($event->end_datetime);

                        $startMinutes = ($startTime->hour * 60) + $startTime->minute;
                        $endMinutes = ($endTime->hour * 60) + $endTime->minute;

                        $gridStartMinutes = $this->startHour * 60;
                        $gridEndMinutes = $this->endHour * 60;

                        if ($startMinutes < $gridEndMinutes && $endMinutes > $gridStartMinutes) {
                            $topMinutes = max($startMinutes - $gridStartMinutes, 0);
                            $durationMinutes = min($endMinutes, $gridEndMinutes) - max($startMinutes, $gridStartMinutes);

                            $event->grid_top = ($topMinutes / 60) * $this->hourHeight;
                            $event->grid_height = ($durationMinutes / 60) * $this->hourHeight;

                            $itemsByDay[$dateKey]['timed']->push($event);
                        }
                    }
                }
            }
        }

        if (in_array($this->itemType, ['all', 'projects'])) {
            $projects = Project::query()
                ->where('user_id', $user->id)
                ->with(['tags', 'tasks'])
                ->where(function ($query) use ($weekStart, $weekEnd) {
                    $query->whereBetween('start_date', [$weekStart, $weekEnd])
                        ->orWhereBetween('end_date', [$weekStart, $weekEnd]);
                })
                ->get();

            foreach ($projects as $project) {
                $project->item_type = 'project';
                $dateKey = Carbon::parse($project->start_date)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    $itemsByDay[$dateKey]['all_day']->push($project);
                }
            }
        }

        return $itemsByDay;
    }
}; ?>

<div class="space-y-4">
    <!-- View Switcher -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex gap-2">
            <button
                wire:click="switchView('list')"
                class="px-3 py-2 rounded {{ $viewMode === 'list' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }}"
                title="List View"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <button
                wire:click="switchView('kanban')"
                class="px-3 py-2 rounded {{ $viewMode === 'kanban' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }}"
                title="Kanban View"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                </svg>
            </button>
            <button
                wire:click="switchView('weekly')"
                class="px-3 py-2 rounded {{ $viewMode === 'weekly' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }}"
                title="Weekly Timegrid View"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </button>
        </div>
    </div>

    <!-- List View -->
    @if($viewMode === 'list')
        <div class="space-y-4">
            <!-- Date Navigation -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $currentDate->format('M d, Y') }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button variant="ghost" size="sm" wire:click="goToTodayDate">
                            Today
                        </flux:button>
                        <flux:button variant="ghost" size="sm" icon="chevron-left" wire:click="previousDay">
                        </flux:button>
                        <flux:button variant="ghost" size="sm" icon="chevron-right" wire:click="nextDay">
                        </flux:button>
                    </div>
                </div>
            </div>

            @forelse($this->items as $item)
                @if($item->item_type === 'task')
                    <x-workspace.task-card :task="$item" />
                @elseif($item->item_type === 'event')
                    <x-workspace.event-card :event="$item" />
                @elseif($item->item_type === 'project')
                    <x-workspace.project-card :project="$item" />
                @endif
            @empty
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">No items found</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Get started by creating a new task, event, or project.
                    </p>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Kanban View -->
    @if($viewMode === 'kanban')
        <!-- Date Navigation -->
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden mb-4">
            <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $currentDate->format('M d, Y') }}
                    </h3>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" size="sm" wire:click="goToTodayDate">
                        Today
                    </flux:button>
                    <flux:button variant="ghost" size="sm" icon="chevron-left" wire:click="previousDay">
                    </flux:button>
                    <flux:button variant="ghost" size="sm" icon="chevron-right" wire:click="nextDay">
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach(['to_do', 'doing', 'done'] as $status)
                <div class="bg-zinc-100 dark:bg-zinc-900 rounded-lg p-4"
                     x-data="{ draggingOver: false }"
                     @dragover.prevent="draggingOver = true"
                     @dragleave="draggingOver = false"
                     @drop.prevent="
                         draggingOver = false;
                         const itemId = $event.dataTransfer.getData('itemId');
                         const itemType = $event.dataTransfer.getData('itemType');

                         // Prevent events from being dropped in 'doing' column
                         if (itemType === 'event' && '{{ $status }}' === 'doing') {
                             return;
                         }

                         $wire.updateItemStatus(parseInt(itemId), itemType, '{{ $status }}');
                     "
                     :class="{ 'ring-2 ring-blue-500': draggingOver }"
                >
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                        {{ match($status) {
                            'to_do' => 'To Do',
                            'doing' => 'In Progress',
                            'done' => 'Done',
                        } }}
                        <span class="text-sm text-zinc-500 dark:text-zinc-400 ml-2">
                            ({{ $this->itemsByStatus[$status]->count() }})
                        </span>
                    </h3>

                    <div class="space-y-3">
                        @foreach($this->itemsByStatus[$status] as $item)
                            <div
                                wire:key="item-{{ $item->item_type }}-{{ $item->id }}"
                                draggable="true"
                                @dragstart="
                                    $event.dataTransfer.effectAllowed = 'move';
                                    $event.dataTransfer.setData('itemId', {{ $item->id }});
                                    $event.dataTransfer.setData('itemType', '{{ $item->item_type }}');
                                "
                                class="cursor-move"
                            >
                                @if($item->item_type === 'task')
                                    <x-workspace.task-card :task="$item" />
                                @elseif($item->item_type === 'event')
                                    <x-workspace.event-card :event="$item" />
                                @elseif($item->item_type === 'project')
                                    <x-workspace.project-card :project="$item" />
                                @endif
                            </div>
                        @endforeach

                        @if($this->itemsByStatus[$status]->isEmpty())
                            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400 text-sm">
                                No items
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Weekly Timegrid View -->
    @if($viewMode === 'weekly')
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

                                     $wire.updateItemDateTime(parseInt(itemId), itemType, newStart, newEnd);
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

                                                        $wire.updateItemDuration({{ $item->id }}, '{{ $item->item_type }}', snappedDuration);

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
    @endif

    <!-- Floating Action Button -->
    <div class="fixed bottom-8 right-8">
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Create
        </flux:button>
    </div>
</div>

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

    // Weekly calendar view properties
    public ?Carbon $weekStartDate = null;

    // Date navigation for list and kanban views
    public ?Carbon $currentDate = null;

    public function mount(): void
    {
        $this->weekStartDate = now()->startOfWeek();
        $this->currentDate = now();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function goToTodayDate(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban'])) {
            $this->currentDate = now();
        }
    }

    public function previousDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->subDay();
        }
    }

    public function nextDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->addDay();
        }
    }

    #[On('switch-to-day-view')]
    public function switchToDayView(string $date): void
    {
        // This method is kept for backward compatibility
        // Calendar now dispatches date-focused directly, but other components might still use this
        $this->viewMode = 'list';
        $this->currentDate = Carbon::parse($date);
    }

    #[On('date-focused')]
    public function updateCurrentDate(string $date): void
    {
        $parsedDate = Carbon::parse($date);

        // Update the appropriate date property based on current view mode
        if (in_array($this->viewMode, ['list', 'kanban'])) {
            $this->currentDate = $parsedDate;
        } elseif ($this->viewMode === 'weekly') {
            // For weekly view, update to the start of the week containing the clicked date
            $this->weekStartDate = $parsedDate->copy()->startOfWeek();
        }
    }

    #[On('switch-to-week-view')]
    public function switchToWeekView(string $weekStart): void
    {
        $this->viewMode = 'weekly';
        $this->weekStartDate = Carbon::parse($weekStart);
    }

    #[On('update-item-status')]
    public function handleUpdateItemStatus(int $itemId, string $itemType, string $newStatus): void
    {
        $this->updateItemStatus($itemId, $itemType, $newStatus);
    }

    #[On('update-item-datetime')]
    public function handleUpdateItemDateTime(int $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        $this->updateItemDateTime($itemId, $itemType, $newStart, $newEnd);
    }

    #[On('update-item-duration')]
    public function handleUpdateItemDuration(int $itemId, string $itemType, int $newDurationMinutes): void
    {
        $this->updateItemDuration($itemId, $itemType, $newDurationMinutes);
    }

    #[On('item-updated')]
    #[On('item-created')]
    public function refreshItems(): void
    {
        // Clear computed property cache to force recalculation with fresh data
        unset($this->items);
        unset($this->filteredItems);
    }

    public function updateItemDateTime(int $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        try {
            $model = match ($itemType) {
                'task' => Task::findOrFail($itemId),
                'event' => Event::findOrFail($itemId),
                'project' => Project::findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            if ($itemType === 'task') {
                if ($newStart) {
                    $startDateTime = Carbon::parse($newStart);
                    $model->start_date = $startDateTime->toDateString();
                    $model->start_time = $startDateTime->format('H:i:s');
                } else {
                    $model->start_date = null;
                    $model->start_time = null;
                }

                if ($newEnd) {
                    $model->end_date = Carbon::parse($newEnd)->toDateString();
                    // Calculate duration from start and end times
                    if ($model->start_date && $model->start_time) {
                        $startDateTime = Carbon::parse($model->start_date.' '.$model->start_time);
                        $endDateTime = Carbon::parse($newEnd);
                        $model->duration = $startDateTime->diffInMinutes($endDateTime);
                    }
                } else {
                    $model->end_date = null;
                }
            } elseif ($itemType === 'event') {
                if ($newStart) {
                    $model->start_datetime = Carbon::parse($newStart);
                }
                if ($newEnd) {
                    $model->end_datetime = Carbon::parse($newEnd);
                } elseif ($newStart) {
                    // Auto-calculate if start provided but no end
                    $model->end_datetime = Carbon::parse($newStart)->addHour();
                }
            } elseif ($itemType === 'project') {
                if ($newStart) {
                    $model->start_date = Carbon::parse($newStart)->toDateString();
                } else {
                    $model->start_date = null;
                }
                if ($newEnd) {
                    $model->end_date = Carbon::parse($newEnd)->toDateString();
                } else {
                    $model->end_date = null;
                }
            }

            $model->save();
            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Item updated successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to update item datetime', ['error' => $e->getMessage(), 'itemId' => $itemId, 'itemType' => $itemType]);
            $this->dispatch('notify', message: 'Failed to update item', type: 'error');
        }
    }

    public function updateItemDuration(int $itemId, string $itemType, int $newDurationMinutes): void
    {
        try {
            // Enforce minimum duration of 30 minutes
            $newDurationMinutes = max(30, $newDurationMinutes);

            // Snap to 30-minute grid intervals
            $newDurationMinutes = round($newDurationMinutes / 30) * 30;
            $newDurationMinutes = max(30, $newDurationMinutes); // Ensure still at least 30 after snapping

            $model = match ($itemType) {
                'task' => Task::findOrFail($itemId),
                'event' => Event::findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            if ($itemType === 'task') {
                // For tasks, update duration and recalculate end_date if needed
                $model->duration = $newDurationMinutes;

                if ($model->start_date && $model->start_time) {
                    $startDateString = Carbon::parse($model->start_date)->format('Y-m-d');
                    $startDateTime = Carbon::parse($startDateString.' '.$model->start_time);
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
            $this->dispatch('notify', message: 'Duration updated successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to update item duration', ['error' => $e->getMessage(), 'itemId' => $itemId, 'itemType' => $itemType]);
            $this->dispatch('notify', message: 'Failed to update duration', type: 'error');
        }
    }

    public function updateItemStatus(int $itemId, string $itemType, string $newStatus): void
    {
        try {
            // Prevent events from being dropped in 'doing' column
            if ($itemType === 'event' && $newStatus === 'doing') {
                $this->dispatch('notify', message: 'Events cannot be moved to In Progress', type: 'warning');

                return;
            }

            $model = match ($itemType) {
                'task' => Task::findOrFail($itemId),
                'event' => Event::findOrFail($itemId),
                'project' => Project::findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

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
                    $this->dispatch('item-updated');
                    $this->dispatch('notify', message: 'Status updated successfully', type: 'success');
                }
            } elseif ($itemType === 'event') {
                $statusEnum = match ($newStatus) {
                    'to_do' => EventStatus::Scheduled,
                    'done' => EventStatus::Completed,
                    default => null,
                };

                if ($statusEnum) {
                    $model->update(['status' => $statusEnum]);
                    $this->dispatch('item-updated');
                    $this->dispatch('notify', message: 'Status updated successfully', type: 'success');
                }
            }
            // Projects don't have status, so no update needed
        } catch (\Exception $e) {
            \Log::error('Failed to update item status', ['error' => $e->getMessage(), 'itemId' => $itemId, 'itemType' => $itemType]);
            $this->dispatch('notify', message: 'Failed to update status', type: 'error');
        }
    }

    #[Computed]
    public function items(): Collection
    {
        $user = auth()->user();
        $items = collect();

        // Always fetch all tasks
        $tasks = Task::query()
            ->accessibleBy($user)
            ->with(['project', 'tags', 'event'])
            ->get()
            ->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_date ?? $task->created_at;

                return $task;
            });

        // Always fetch all events
        $events = Event::query()
            ->accessibleBy($user)
            ->with(['tags'])
            ->get()
            ->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime;

                return $event;
            });

        // Always fetch all projects
        $projects = Project::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks'])
            ->get()
            ->map(function ($project) {
                $project->item_type = 'project';
                $project->sort_date = $project->created_at;

                return $project;
            });

        return $items->merge($tasks)->merge($events)->merge($projects);
    }

    #[Computed]
    public function availableTags(): Collection
    {
        $user = auth()->user();

        return Tag::whereHas('tasks', function ($query) use ($user) {
            $query->accessibleBy($user);
        })
            ->orWhereHas('events', function ($query) use ($user) {
                $query->accessibleBy($user);
            })
            ->orWhereHas('projects', function ($query) use ($user) {
                $query->accessibleBy($user);
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableProjects(): Collection
    {
        return Project::accessibleBy(auth()->user())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function filteredItems(): Collection
    {
        // For weekly view, return all items (weekly view handles its own filtering)
        if ($this->viewMode === 'weekly') {
            return $this->items;
        }

        // For list/kanban views, filter items by currentDate
        if (! in_array($this->viewMode, ['list', 'kanban']) || ! $this->currentDate) {
            return $this->items;
        }

        $targetDate = $this->currentDate->format('Y-m-d');

        return $this->items->filter(function ($item) use ($targetDate) {
            if ($item->item_type === 'task') {
                // If task has no dates, don't show in date-filtered view
                if (! $item->start_date && ! $item->end_date) {
                    return false;
                }

                $startDate = $item->start_date instanceof Carbon
                    ? $item->start_date->format('Y-m-d')
                    : Carbon::parse($item->start_date)->format('Y-m-d');

                $endDate = null;
                if ($item->end_date) {
                    $endDate = $item->end_date instanceof Carbon
                        ? $item->end_date->format('Y-m-d')
                        : Carbon::parse($item->end_date)->format('Y-m-d');
                }

                // Include task if it starts, ends, or spans the target date
                return ($startDate === $targetDate) ||
                       ($endDate === $targetDate) ||
                       ($endDate && $startDate <= $targetDate && $endDate >= $targetDate);
            } elseif ($item->item_type === 'event') {
                if (! $item->start_datetime) {
                    return false;
                }

                $startDate = $item->start_datetime instanceof Carbon
                    ? $item->start_datetime->format('Y-m-d')
                    : Carbon::parse($item->start_datetime)->format('Y-m-d');

                $endDate = null;
                if ($item->end_datetime) {
                    $endDate = $item->end_datetime instanceof Carbon
                        ? $item->end_datetime->format('Y-m-d')
                        : Carbon::parse($item->end_datetime)->format('Y-m-d');
                }

                // Include event if it starts, ends, or spans the target date
                return ($startDate === $targetDate) ||
                       ($endDate === $targetDate) ||
                       ($endDate && $startDate <= $targetDate && $endDate >= $targetDate);
            } elseif ($item->item_type === 'project') {
                // If project has no dates, don't show in date-filtered view
                if (! $item->start_date && ! $item->end_date) {
                    return false;
                }

                $startDate = $item->start_date instanceof Carbon
                    ? $item->start_date->format('Y-m-d')
                    : Carbon::parse($item->start_date)->format('Y-m-d');

                $endDate = null;
                if ($item->end_date) {
                    $endDate = $item->end_date instanceof Carbon
                        ? $item->end_date->format('Y-m-d')
                        : Carbon::parse($item->end_date)->format('Y-m-d');
                }

                // Include project if it starts, ends, or spans the target date
                return ($startDate === $targetDate) ||
                       ($endDate === $targetDate) ||
                       ($endDate && $startDate <= $targetDate && $endDate >= $targetDate);
            }

            return false;
        });
    }

    #[Computed]
    public function itemsByStatus(): array
    {
        $items = $this->filteredItems;

        return [
            'to_do' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['to_do', 'scheduled', 'tentative'])),
            'doing' => $items->filter(fn ($item) => $item->status?->value === 'doing'),
            'done' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['done', 'completed'])),
        ];
    }
}; ?>

<div
    class="space-y-4 px-4"
    wire:loading.class="opacity-50"
    wire:target="switchView,goToTodayDate,previousDay,nextDay,updateCurrentDate"
    x-data="{}"
>
    <!-- View Switcher -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex gap-2" role="group" aria-label="View mode selection">
            <button
                wire:click="switchView('list')"
                wire:loading.attr="disabled"
                wire:target="switchView"
                aria-label="Switch to list view"
                aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}"
                class="px-3 py-2 rounded transition-colors {{ $viewMode === 'list' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }} disabled:opacity-50 disabled:cursor-not-allowed"
                title="List View"
            >
                <span wire:loading.remove wire:target="switchView">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </span>
                <span wire:loading wire:target="switchView">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
            <button
                wire:click="switchView('kanban')"
                wire:loading.attr="disabled"
                wire:target="switchView"
                aria-label="Switch to kanban view"
                aria-pressed="{{ $viewMode === 'kanban' ? 'true' : 'false' }}"
                class="px-3 py-2 rounded transition-colors {{ $viewMode === 'kanban' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }} disabled:opacity-50 disabled:cursor-not-allowed"
                title="Kanban View"
            >
                <span wire:loading.remove wire:target="switchView">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                    </svg>
                </span>
                <span wire:loading wire:target="switchView">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
            <button
                wire:click="switchView('weekly')"
                wire:loading.attr="disabled"
                wire:target="switchView"
                aria-label="Switch to weekly timegrid view"
                aria-pressed="{{ $viewMode === 'weekly' ? 'true' : 'false' }}"
                class="px-3 py-2 rounded transition-colors {{ $viewMode === 'weekly' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }} disabled:opacity-50 disabled:cursor-not-allowed"
                title="Weekly Timegrid View"
            >
                <span wire:loading.remove wire:target="switchView">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </span>
                <span wire:loading wire:target="switchView">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </div>
    </div>

    <!-- Loading Overlay for View Switching -->
    <div wire:loading wire:target="switchView" class="fixed inset-0 bg-black/10 dark:bg-black/20 z-40 flex items-center justify-center pointer-events-none">
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-lg flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Switching view...</span>
        </div>
    </div>

    <!-- Loading Overlay for Date Navigation -->
    <div wire:loading wire:target="goToTodayDate,previousDay,nextDay,updateCurrentDate" class="fixed inset-0 bg-black/10 dark:bg-black/20 z-40 flex items-center justify-center pointer-events-none">
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-lg flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Loading date...</span>
        </div>
    </div>

    <!-- List View -->
    <div>
        @if($viewMode === 'list')
            <livewire:workspace.list-view
                :items="$this->filteredItems"
                :current-date="$currentDate"
                wire:key="list-view-{{ $currentDate->format('Y-m-d') }}"
            />
        @endif
    </div>

    <!-- Kanban View -->
    @if($viewMode === 'kanban')
        <livewire:workspace.kanban-view
            :items="$this->filteredItems"
            :items-by-status="$this->itemsByStatus"
            :current-date="$currentDate"
            wire:key="kanban-view-{{ $currentDate->format('Y-m-d') }}"
        />
    @endif

    <!-- Weekly Timegrid View -->
    <div wire:transition="fade">
        @if($viewMode === 'weekly')
            <livewire:workspace.weekly-view
                :items="$this->items"
                :week-start-date="$weekStartDate"
                wire:key="weekly-view-{{ $weekStartDate->format('Y-m-d') }}"
            />
        @endif
    </div>

    <!-- Create Item Modal -->
    <livewire:workspace.create-item-modal />
</div>

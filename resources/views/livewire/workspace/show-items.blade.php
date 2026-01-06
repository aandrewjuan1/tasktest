<?php

use App\Enums\EventStatus;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Project;
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

    // Filter properties
    #[Url]
    public ?string $filterType = null;
    #[Url]
    public ?string $filterPriority = null;
    #[Url]
    public ?string $filterStatus = null;

    // Sort properties
    #[Url]
    public ?string $sortBy = null;
    #[Url]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->weekStartDate = now()->startOfWeek();
        $this->currentDate = now();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
        // Reset filters and sorts when switching views
        $this->clearAll();
    }

    public function goToTodayDate(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban'])) {
            $this->currentDate = now();
            $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
        }
    }

    public function previousDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->subDay();
            $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
        }
    }

    public function nextDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->addDay();
            $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
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

    #[On('task-updated')]
    #[On('task-deleted')]
    #[On('item-created')]
    public function refreshItemsFromTaskDetail(): void
    {
        // Trigger a re-render so the list reflects changes made in other components
        // like the task detail modal or the create-item component.
        // No additional logic is needed; computed queries will be re-run.
    }

    #[On('reset-filters-sorts')]
    public function resetFiltersAndSorts(): void
    {
        $this->clearAll();
    }

    #[On('set-filter-type')]
    public function handleSetFilterType(...$params): void
    {
        $type = $params[0] ?? null;
        $this->setFilterType($type);
    }

    #[On('set-filter-priority')]
    public function handleSetFilterPriority(...$params): void
    {
        $priority = $params[0] ?? null;
        $this->setFilterPriority($priority);
    }

    #[On('set-filter-status')]
    public function handleSetFilterStatus(...$params): void
    {
        $status = $params[0] ?? null;
        $this->setFilterStatus($status);
    }

    #[On('set-sort-by')]
    public function handleSetSortBy(...$params): void
    {
        $sortBy = $params[0] ?? null;
        $this->setSortBy($sortBy);
    }

    #[On('clear-all-filters-sorts')]
    public function handleClearAll(): void
    {
        $this->clearAll();
    }

    public function setFilterType(?string $type): void
    {
        $this->filterType = $type;
        // Livewire will automatically recompute computed properties
    }

    public function setFilterPriority(?string $priority): void
    {
        $this->filterPriority = $priority;
        // Livewire will automatically recompute computed properties
    }

    public function setFilterStatus(?string $status): void
    {
        $this->filterStatus = $status;
        // Livewire will automatically recompute computed properties
    }

    public function setSortBy(?string $sortBy): void
    {
        if ($this->sortBy === $sortBy) {
            // Toggle direction if same sort field
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $sortBy;
            $this->sortDirection = 'asc';
        }
        // Livewire will automatically recompute computed properties
    }

    public function clearFilters(): void
    {
        $this->filterType = null;
        $this->filterPriority = null;
        $this->filterStatus = null;
        // Livewire will automatically recompute computed properties
    }

    public function clearSorting(): void
    {
        $this->sortBy = null;
        $this->sortDirection = 'asc';
        // Livewire will automatically recompute computed properties
    }

    public function clearAll(): void
    {
        $this->clearFilters();
        $this->clearSorting();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return ($this->filterType && $this->filterType !== 'all')
            || ($this->filterPriority && $this->filterPriority !== 'all')
            || ($this->filterStatus && $this->filterStatus !== 'all');
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
                    $model->start_datetime = Carbon::parse($newStart);
                } else {
                    $model->start_datetime = null;
                }

                if ($newEnd) {
                    $model->end_datetime = Carbon::parse($newEnd);
                    // Calculate duration from start and end times
                    if ($model->start_datetime) {
                        $model->duration = $model->start_datetime->diffInMinutes($model->end_datetime);
                    }
                } else {
                    $model->end_datetime = null;
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
                    'doing' => EventStatus::Ongoing,
                    'done' => EventStatus::Completed,
                    'scheduled' => EventStatus::Scheduled,
                    'ongoing' => EventStatus::Ongoing,
                    'completed' => EventStatus::Completed,
                    'cancelled' => EventStatus::Cancelled,
                    'tentative' => EventStatus::Tentative,
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
    public function filteredTasks(): Collection
    {
        $user = auth()->user();

        $query = Task::query()
            ->accessibleBy($user)
            ->with(['project', 'tags', 'event']);

        // Apply filters
        if ($this->filterType === 'task' || !$this->filterType || $this->filterType === 'all') {
            // Only apply filters if we're showing tasks or all items
            if ($this->filterPriority) {
                $query->filterByPriority($this->filterPriority);
            }
            if ($this->filterStatus) {
                $query->filterByStatus($this->filterStatus);
            }
        } else {
            // If filtering by type and it's not 'task', return empty collection
            return collect();
        }

        // Apply date filter for list/kanban views
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $query->dateFilter($this->currentDate);
        }

        // Apply sorting
        $query->orderByField($this->sortBy, $this->sortDirection);

        return $query->get()->map(function ($task) {
            $task->item_type = 'task';
            $task->sort_date = $task->end_datetime ?? $task->created_at;

            return $task;
        });
    }

    #[Computed]
    public function filteredEvents(): Collection
    {
        $user = auth()->user();

        $query = Event::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks']);

        // Apply filters
        if ($this->filterType === 'event' || !$this->filterType || $this->filterType === 'all') {
            // Only apply filters if we're showing events or all items
            if ($this->filterPriority) {
                $query->filterByPriority($this->filterPriority);
            }
            if ($this->filterStatus) {
                $query->filterByStatus($this->filterStatus);
            }
        } else {
            // If filtering by type and it's not 'event', return empty collection
            return collect();
        }

        // Apply date filter for list/kanban views
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $query->dateFilter($this->currentDate);
        }

        // Apply sorting
        $query->orderByField($this->sortBy, $this->sortDirection);

        return $query->get()->map(function ($event) {
            $event->item_type = 'event';
            $event->sort_date = $event->start_datetime;

            return $event;
        });
    }

    #[Computed]
    public function filteredProjects(): Collection
    {
        $user = auth()->user();

        $query = Project::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks']);

        // Apply filters
        if ($this->filterType === 'project' || !$this->filterType || $this->filterType === 'all') {
            // Only apply filters if we're showing projects or all items
            if ($this->filterPriority) {
                $query->filterByPriority($this->filterPriority);
            }
            if ($this->filterStatus) {
                $query->filterByStatus($this->filterStatus);
            }
        } else {
            // If filtering by type and it's not 'project', return empty collection
            return collect();
        }

        // Apply date filter for list/kanban views
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $query->dateFilter($this->currentDate);
        }

        // Apply sorting
        $query->orderByField($this->sortBy, $this->sortDirection);

        return $query->get()->map(function ($project) {
            $project->item_type = 'project';
            $project->sort_date = $project->created_at;

            return $project;
        });
    }

    #[Computed]
    public function filteredItems(): Collection
    {
        // For weekly view, apply filters and sorting but not date filtering (weekly view handles its own date filtering)
        if ($this->viewMode === 'weekly') {
            $user = auth()->user();

            // Get filtered tasks (without date filter)
            $taskQuery = Task::query()
                ->accessibleBy($user)
                ->with(['project', 'tags', 'event']);

            if ($this->filterType === 'task' || !$this->filterType || $this->filterType === 'all') {
                if ($this->filterPriority) {
                    $taskQuery->filterByPriority($this->filterPriority);
                }
                if ($this->filterStatus) {
                    $taskQuery->filterByStatus($this->filterStatus);
                }
            } else {
                $taskQuery->whereRaw('1 = 0'); // Return empty result
            }

            $taskQuery->orderByField($this->sortBy, $this->sortDirection);
            $tasks = $taskQuery->get()->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_datetime ?? $task->created_at;
                return $task;
            });

            // Get filtered events (without date filter)
            $eventQuery = Event::query()
                ->accessibleBy($user)
                ->with(['tags', 'tasks']);

            if ($this->filterType === 'event' || !$this->filterType || $this->filterType === 'all') {
                if ($this->filterPriority) {
                    $eventQuery->filterByPriority($this->filterPriority);
                }
                if ($this->filterStatus) {
                    $eventQuery->filterByStatus($this->filterStatus);
                }
            } else {
                $eventQuery->whereRaw('1 = 0'); // Return empty result
            }

            $eventQuery->orderByField($this->sortBy, $this->sortDirection);
            $events = $eventQuery->get()->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime;
                return $event;
            });

            // Get filtered projects (without date filter)
            $projectQuery = Project::query()
                ->accessibleBy($user)
                ->with(['tags', 'tasks']);

            if ($this->filterType === 'project' || !$this->filterType || $this->filterType === 'all') {
                if ($this->filterPriority) {
                    $projectQuery->filterByPriority($this->filterPriority);
                }
                if ($this->filterStatus) {
                    $projectQuery->filterByStatus($this->filterStatus);
                }
            } else {
                $projectQuery->whereRaw('1 = 0'); // Return empty result
            }

            $projectQuery->orderByField($this->sortBy, $this->sortDirection);
            $projects = $projectQuery->get()->map(function ($project) {
                $project->item_type = 'project';
                $project->sort_date = $project->created_at;
                return $project;
            });

            return collect()->merge($tasks)->merge($events)->merge($projects);
        }

        // For list/kanban views, merge filtered collections
        return collect()
            ->merge($this->filteredTasks)
            ->merge($this->filteredEvents)
            ->merge($this->filteredProjects);
    }

    #[Computed]
    public function itemsByStatus(): array
    {
        $items = $this->filteredItems;

        return [
            'to_do' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['to_do', 'scheduled', 'tentative'])),
            'doing' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['doing', 'ongoing'])),
            'done' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['done', 'completed'])),
        ];
    }
}; ?>

<div
    wire:key="show-items-component"
    class="space-y-4 px-4"
    wire:loading.class="opacity-50"
    wire:target="switchView,goToTodayDate,previousDay,nextDay,updateCurrentDate"
    x-data="{}"
    x-cloak
>

    <!-- Loading Overlay for View Switching -->
    <div
        wire:loading
        wire:target="switchView"
        x-cloak
        class="fixed inset-0 bg-black/10 dark:bg-black/20 z-40 flex items-center justify-center pointer-events-none"
    >
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-lg flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Switching view...</span>
        </div>
    </div>

    <!-- Loading Overlay for Date Navigation -->
    <div
        wire:loading
        wire:target="goToTodayDate,previousDay,nextDay,updateCurrentDate"
        x-cloak
        class="fixed inset-0 bg-black/10 dark:bg-black/20 z-40 flex items-center justify-center pointer-events-none"
    >
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-lg flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Loading date...</span>
        </div>
    </div>

    <!-- List View -->
    @if($viewMode === 'list')
        <div wire:key="list-view-container">
            <livewire:workspace.list-view
                :items="$this->filteredItems"
                :current-date="$currentDate"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                :view-mode="$viewMode"
                wire:key="list-view-{{ $viewMode }}"
            />
        </div>
    @endif

    <!-- Kanban View -->
    @if($viewMode === 'kanban')
        <div wire:key="kanban-view-container">
            <livewire:workspace.kanban-view
                :items="$this->filteredItems"
                :items-by-status="$this->itemsByStatus"
                :current-date="$currentDate"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                :view-mode="$viewMode"
                wire:key="kanban-view-{{ $viewMode }}"
            />
        </div>
    @endif

    <!-- Weekly Timegrid View -->
    @if($viewMode === 'weekly')
        <div wire:key="weekly-view-container-{{ $weekStartDate->format('Y-m-d') }}">
            <livewire:workspace.weekly-view
                :items="$this->filteredItems"
                :week-start-date="$weekStartDate"
                :view-mode="$viewMode"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                wire:key="weekly-view-{{ $weekStartDate->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Create Item -->
    <livewire:workspace.create-item />
</div>

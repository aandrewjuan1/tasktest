<?php

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
    public string $viewMode = 'list'; // list, kanban, weekly_calendar

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $itemType = 'all';

    // Basic filters
    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $priorityFilter = '';

    #[Url(except: '')]
    public string $complexityFilter = '';

    #[Url(except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    // Advanced filters
    #[Url(except: '')]
    public string $tagFilters = '';

    #[Url(except: '')]
    public string $projectFilter = '';

    #[Url(except: '')]
    public string $dateRangeFilter = ''; // today, this_week, this_month, custom, overdue

    #[Url(except: '')]
    public string $customStartDate = '';

    #[Url(except: '')]
    public string $customEndDate = '';

    // Bulk actions
    public array $selectedItems = [];

    public bool $selectAll = false;

    // Weekly calendar view properties
    public ?Carbon $weekStartDate = null;

    public int $startHour = 6;

    public int $endHour = 22;

    public int $hourHeight = 60;

    public int $slotIncrement = 15;

    public function mount(): void
    {
        $this->weekStartDate = now()->startOfWeek();
        $this->loadTimegridSettings();

        // Normalize tagFilters from URL - handle case where it might come as array from URL hydration
        if (is_array($this->tagFilters)) {
            $this->setTagFiltersFromArray($this->tagFilters);
        } elseif (! is_string($this->tagFilters)) {
            $this->tagFilters = '';
        }
    }

    public function loadTimegridSettings(): void
    {
        $settings = auth()->user()->timegridSetting;

        if ($settings) {
            $this->startHour = $settings->start_hour;
            $this->endHour = $settings->end_hour;
            $this->hourHeight = $settings->hour_height;
            $this->slotIncrement = $settings->slot_increment;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingItemType(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPriorityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingComplexityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTagFilters($value): void
    {
        // Normalize array to string if it comes as array from URL hydration
        if (is_array($value)) {
            $this->tagFilters = implode(',', array_filter(
                array_map('trim', $value),
                fn ($name) => ! empty($name)
            ));
        }
        $this->resetPage();
    }

    public function updatedTagFilters(): void
    {
        // Ensure it's always a string after update
        if (is_array($this->tagFilters)) {
            $this->setTagFiltersFromArray($this->tagFilters);
        }
    }

    public function updatingProjectFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateRangeFilter(): void
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

    #[On('switch-to-week-view')]
    public function switchToWeekView(string $weekStart): void
    {
        $this->viewMode = 'weekly';
        $this->weekStartDate = Carbon::parse($weekStart);
    }

    public function openTimegridSettings(): void
    {
        $this->dispatch('open-timegrid-settings');
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
            $model->start_date = Carbon::parse($newStart)->toDateString();
            if ($newEnd) {
                $model->end_date = Carbon::parse($newEnd)->toDateString();
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

    #[Computed]
    public function tagFiltersArray(): array
    {
        if (empty($this->tagFilters)) {
            return [];
        }

        // Handle array case (during URL hydration)
        if (is_array($this->tagFilters)) {
            return array_filter(
                array_map('trim', $this->tagFilters),
                fn ($name) => ! empty($name)
            );
        }

        // Handle string case (comma-separated tag names)
        return array_filter(
            array_map('trim', explode(',', $this->tagFilters)),
            fn ($name) => ! empty($name)
        );
    }

    protected function setTagFiltersFromArray(array $tagNames): void
    {
        $filtered = array_filter(
            array_map('trim', $tagNames),
            fn ($name) => ! empty($name)
        );
        $this->tagFilters = (string) implode(',', $filtered);
    }

    public function toggleTagFilter(string $tagName): void
    {
        $tagArray = $this->tagFiltersArray;

        if (in_array($tagName, $tagArray)) {
            $tagArray = array_values(array_filter($tagArray, fn ($name) => $name !== $tagName));
        } else {
            $tagArray[] = $tagName;
        }

        $this->setTagFiltersFromArray($tagArray);
        $this->resetPage();
    }

    public function removeTagFilter(string $tagName): void
    {
        $tagArray = $this->tagFiltersArray;
        $tagArray = array_values(array_filter($tagArray, fn ($name) => $name !== $tagName));
        $this->setTagFiltersFromArray($tagArray);
        $this->resetPage();
    }

    public function removeStatusFilter(): void
    {
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function removePriorityFilter(): void
    {
        $this->priorityFilter = '';
        $this->resetPage();
    }

    public function removeComplexityFilter(): void
    {
        $this->complexityFilter = '';
        $this->resetPage();
    }

    public function removeProjectFilter(): void
    {
        $this->projectFilter = '';
        $this->resetPage();
    }

    public function removeDateRangeFilter(): void
    {
        $this->dateRangeFilter = '';
        $this->customStartDate = '';
        $this->customEndDate = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->priorityFilter = '';
        $this->complexityFilter = '';
        $this->tagFilters = '';
        $this->projectFilter = '';
        $this->dateRangeFilter = '';
        $this->customStartDate = '';
        $this->customEndDate = '';
        $this->resetPage();
    }

    public function updateTaskStatus(int $taskId, string $newStatus): void
    {
        $task = Task::where('id', $taskId)
            ->where('user_id', auth()->id())
            ->first();

        if ($task) {
            $task->update(['status' => $newStatus]);
            $this->dispatch('task-updated');
        }
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedItems = $this->items->map(fn ($item) => $item->id.'-'.$item->item_type)->toArray();
        } else {
            $this->selectedItems = [];
        }
    }

    public function toggleSelection(string $itemId): void
    {
        if (in_array($itemId, $this->selectedItems)) {
            $this->selectedItems = array_values(array_filter($this->selectedItems, fn ($id) => $id !== $itemId));
        } else {
            $this->selectedItems[] = $itemId;
        }
        $this->selectAll = false;
    }

    public function bulkChangeStatus(string $newStatus): void
    {
        foreach ($this->selectedItems as $itemId) {
            [$id, $type] = explode('-', $itemId);

            if ($type === 'task') {
                Task::where('id', $id)->where('user_id', auth()->id())->update(['status' => $newStatus]);
            } elseif ($type === 'event') {
                Event::where('id', $id)->where('user_id', auth()->id())->update(['status' => $newStatus]);
            }
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        session()->flash('message', 'Items updated successfully!');
    }

    public function bulkChangePriority(string $newPriority): void
    {
        foreach ($this->selectedItems as $itemId) {
            [$id, $type] = explode('-', $itemId);

            if ($type === 'task') {
                Task::where('id', $id)->where('user_id', auth()->id())->update(['priority' => $newPriority]);
            }
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        session()->flash('message', 'Task priorities updated successfully!');
    }

    public function bulkAddTags(array $tagIds): void
    {
        foreach ($this->selectedItems as $itemId) {
            [$id, $type] = explode('-', $itemId);

            $model = match ($type) {
                'task' => Task::where('id', $id)->where('user_id', auth()->id())->first(),
                'event' => Event::where('id', $id)->where('user_id', auth()->id())->first(),
                'project' => Project::where('id', $id)->where('user_id', auth()->id())->first(),
                default => null,
            };

            if ($model) {
                $model->tags()->syncWithoutDetaching($tagIds);
            }
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        session()->flash('message', 'Tags added successfully!');
    }

    public function bulkRemoveTags(array $tagIds): void
    {
        foreach ($this->selectedItems as $itemId) {
            [$id, $type] = explode('-', $itemId);

            $model = match ($type) {
                'task' => Task::where('id', $id)->where('user_id', auth()->id())->first(),
                'event' => Event::where('id', $id)->where('user_id', auth()->id())->first(),
                'project' => Project::where('id', $id)->where('user_id', auth()->id())->first(),
                default => null,
            };

            if ($model) {
                $model->tags()->detach($tagIds);
            }
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        session()->flash('message', 'Tags removed successfully!');
    }

    public function bulkAssignProject(int $projectId): void
    {
        foreach ($this->selectedItems as $itemId) {
            [$id, $type] = explode('-', $itemId);

            if ($type === 'task') {
                Task::where('id', $id)->where('user_id', auth()->id())->update(['project_id' => $projectId]);
            }
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        session()->flash('message', 'Tasks assigned to project successfully!');
    }

    public function bulkDelete(): void
    {
        foreach ($this->selectedItems as $itemId) {
            [$id, $type] = explode('-', $itemId);

            match ($type) {
                'task' => Task::where('id', $id)->where('user_id', auth()->id())->delete(),
                'event' => Event::where('id', $id)->where('user_id', auth()->id())->delete(),
                'project' => Project::where('id', $id)->where('user_id', auth()->id())->delete(),
                default => null,
            };
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        session()->flash('message', 'Items deleted successfully!');
    }

    #[Computed]
    public function items(): Collection
    {
        $user = auth()->user();
        $items = collect();

        // Build queries based on item type filter
        if (in_array($this->itemType, ['all', 'tasks'])) {
            $tasksQuery = Task::query()
                ->where('user_id', $user->id)
                ->with(['project', 'tags', 'event']);

            if ($this->search) {
                $tasksQuery->where(function ($query) {
                    $query->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            }

            if ($this->statusFilter) {
                $tasksQuery->where('status', $this->statusFilter);
            }

            if ($this->priorityFilter) {
                $tasksQuery->where('priority', $this->priorityFilter);
            }

            if ($this->complexityFilter) {
                $tasksQuery->where('complexity', $this->complexityFilter);
            }

            if ($this->projectFilter) {
                $tasksQuery->where('project_id', $this->projectFilter);
            }

            $tagFiltersArray = $this->tagFiltersArray;
            if (! empty($tagFiltersArray)) {
                $tasksQuery->whereHas('tags', function ($query) use ($tagFiltersArray) {
                    $query->whereIn('tags.name', $tagFiltersArray);
                });
            }

            $this->applyDateRangeFilter($tasksQuery, 'end_date');

            $tasks = $tasksQuery->get()->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_date ?? $task->created_at;

                return $task;
            });

            $items = $items->merge($tasks);
        }

        if (in_array($this->itemType, ['all', 'events'])) {
            $eventsQuery = Event::query()
                ->where('user_id', $user->id)
                ->with(['tags']);

            if ($this->search) {
                $eventsQuery->where(function ($query) {
                    $query->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            }

            if ($this->statusFilter) {
                $eventsQuery->where('status', $this->statusFilter);
            }

            $tagFiltersArray = $this->tagFiltersArray;
            if (! empty($tagFiltersArray)) {
                $eventsQuery->whereHas('tags', function ($query) use ($tagFiltersArray) {
                    $query->whereIn('tags.id', $tagFiltersArray);
                });
            }

            $this->applyDateRangeFilter($eventsQuery, 'start_datetime');

            $events = $eventsQuery->get()->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime;

                return $event;
            });

            $items = $items->merge($events);
        }

        if (in_array($this->itemType, ['all', 'projects'])) {
            $projectsQuery = Project::query()
                ->where('user_id', $user->id)
                ->with(['tags', 'tasks']);

            if ($this->search) {
                $projectsQuery->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            }

            $tagFiltersArray = $this->tagFiltersArray;
            if (! empty($tagFiltersArray)) {
                $projectsQuery->whereHas('tags', function ($query) use ($tagFiltersArray) {
                    $query->whereIn('tags.id', $tagFiltersArray);
                });
            }

            $this->applyDateRangeFilter($projectsQuery, 'end_date');

            $projects = $projectsQuery->get()->map(function ($project) {
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

    protected function applyDateRangeFilter($query, string $dateField): void
    {
        if (! $this->dateRangeFilter) {
            return;
        }

        $now = now();

        match ($this->dateRangeFilter) {
            'today' => $query->whereDate($dateField, $now->toDateString()),
            'this_week' => $query->whereBetween($dateField, [$now->startOfWeek(), $now->endOfWeek()]),
            'this_month' => $query->whereMonth($dateField, $now->month)
                ->whereYear($dateField, $now->year),
            'overdue' => $query->where($dateField, '<', $now)
                ->where(function ($q) {
                    $q->whereNotIn('status', ['done', 'completed']);
                }),
            'custom' => $this->customStartDate && $this->customEndDate
                ? $query->whereBetween($dateField, [$this->customStartDate, $this->customEndDate])
                : null,
            default => null,
        };
    }

    #[Computed]
    public function availableTags(): Collection
    {
        return Tag::orderBy('name')->get();
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
                });

            // Apply filters
            if ($this->search) {
                $tasks->where(function ($query) {
                    $query->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            }
            if ($this->statusFilter) {
                $tasks->where('status', $this->statusFilter);
            }
            if ($this->priorityFilter) {
                $tasks->where('priority', $this->priorityFilter);
            }
            if ($this->complexityFilter) {
                $tasks->where('complexity', $this->complexityFilter);
            }
            if ($this->projectFilter) {
                $tasks->where('project_id', $this->projectFilter);
            }
            $tagFiltersArray = $this->tagFiltersArray;
            if (! empty($tagFiltersArray)) {
                $tasks->whereHas('tags', function ($query) use ($tagFiltersArray) {
                    $query->whereIn('tags.id', $tagFiltersArray);
                });
            }

            $this->applyDateRangeFilter($tasks, 'end_date');

            foreach ($tasks->get() as $task) {
                $task->item_type = 'task';
                $dateKey = Carbon::parse($task->start_date)->format('Y-m-d');

                if (isset($itemsByDay[$dateKey])) {
                    $itemsByDay[$dateKey]['all_day']->push($task);
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
                });

            // Apply filters
            if ($this->search) {
                $events->where(function ($query) {
                    $query->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            }
            if ($this->statusFilter) {
                $events->where('status', $this->statusFilter);
            }
            $tagFiltersArray = $this->tagFiltersArray;
            if (! empty($tagFiltersArray)) {
                $events->whereHas('tags', function ($query) use ($tagFiltersArray) {
                    $query->whereIn('tags.id', $tagFiltersArray);
                });
            }

            $this->applyDateRangeFilter($events, 'start_datetime');

            foreach ($events->get() as $event) {
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
                });

            // Apply filters
            if ($this->search) {
                $projects->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            }
            $tagFiltersArray = $this->tagFiltersArray;
            if (! empty($tagFiltersArray)) {
                $projects->whereHas('tags', function ($query) use ($tagFiltersArray) {
                    $query->whereIn('tags.id', $tagFiltersArray);
                });
            }

            $this->applyDateRangeFilter($projects, 'end_date');

            foreach ($projects->get() as $project) {
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
    <!-- View Switcher and Search Bar -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-4">
            <!-- View Switcher -->
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

            <!-- Bulk Actions Checkbox -->
            @if($viewMode === 'list')
                <div class="flex items-center gap-2">
                    <flux:checkbox wire:model.live="selectAll" wire:change="toggleSelectAll" label="Select All" />
                </div>
            @endif
        </div>

        <!-- Search -->
        <div class="mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search tasks, events, and projects..."
                type="search"
            />
        </div>

        <!-- Filters Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Item Type Filter -->
            <flux:select wire:model.live="itemType" label="Type">
                <option value="all">All Items</option>
                <option value="tasks">Tasks Only</option>
                <option value="events">Events Only</option>
                <option value="projects">Projects Only</option>
            </flux:select>

            <!-- Status Filter -->
            <flux:select wire:model.live="statusFilter" label="Status">
                <option value="">All Statuses</option>
                <option value="to_do">To Do</option>
                <option value="doing">In Progress</option>
                <option value="done">Done</option>
                <option value="scheduled">Scheduled</option>
                <option value="cancelled">Cancelled</option>
                <option value="completed">Completed</option>
                <option value="tentative">Tentative</option>
            </flux:select>

            <!-- Priority Filter (for tasks) -->
            @if(in_array($itemType, ['all', 'tasks']))
                <flux:select wire:model.live="priorityFilter" label="Priority">
                    <option value="">All Priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </flux:select>
            @endif

            <!-- Complexity Filter (for tasks) -->
            @if(in_array($itemType, ['all', 'tasks']))
                <flux:select wire:model.live="complexityFilter" label="Complexity">
                    <option value="">All Complexities</option>
                    <option value="simple">Simple</option>
                    <option value="moderate">Moderate</option>
                    <option value="complex">Complex</option>
                </flux:select>
            @endif

            <!-- Project Filter -->
            @if(in_array($itemType, ['all', 'tasks']))
                <flux:select wire:model.live="projectFilter" label="Project">
                    <option value="">All Projects</option>
                    @foreach($this->availableProjects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            <!-- Date Range Filter -->
            <flux:select wire:model.live="dateRangeFilter" label="Date Range">
                <option value="">All Dates</option>
                <option value="today">Today</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="overdue">Overdue</option>
                <option value="custom">Custom Range</option>
            </flux:select>

            <!-- Sort -->
            <flux:select wire:model.live="sortBy" label="Sort By">
                <option value="created_at">Created Date</option>
                <option value="due_date">Due Date</option>
                <option value="title">Title</option>
                <option value="priority">Priority</option>
            </flux:select>
        </div>

        <!-- Custom Date Range -->
        @if($dateRangeFilter === 'custom')
            <div class="grid grid-cols-2 gap-4 mt-4">
                <flux:input wire:model.live="customStartDate" label="Start Date" type="date" />
                <flux:input wire:model.live="customEndDate" label="End Date" type="date" />
            </div>
        @endif

        <!-- Tag Filter -->
        <div class="mt-4">
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Tags</label>
            <div class="flex flex-wrap gap-2">
                @foreach($this->availableTags as $tag)
                    <button
                        wire:click="toggleTagFilter('{{ $tag->name }}')"
                        class="px-3 py-1 text-sm rounded {{ in_array($tag->name, $this->tagFiltersArray) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }}"
                    >
                        {{ $tag->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Active Filters Chips -->
        @php
            $tagFiltersArray = $this->tagFiltersArray;
        @endphp
        @if($statusFilter || $priorityFilter || $complexityFilter || !empty($tagFiltersArray) || $projectFilter || $dateRangeFilter)
            <div class="flex flex-wrap gap-2 mt-4">
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Active filters:</span>

                <!-- Status Filter -->
                @if($statusFilter)
                    <span class="inline-flex items-center gap-1 px-3 py-1 text-sm rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        Status: {{ match($statusFilter) {
                            'to_do' => 'To Do',
                            'doing' => 'In Progress',
                            'done' => 'Done',
                            'scheduled' => 'Scheduled',
                            'cancelled' => 'Cancelled',
                            'completed' => 'Completed',
                            'tentative' => 'Tentative',
                            default => $statusFilter,
                        } }}
                        <button wire:click="removeStatusFilter" class="hover:text-blue-900 dark:hover:text-blue-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endif

                <!-- Priority Filter -->
                @if($priorityFilter)
                    <span class="inline-flex items-center gap-1 px-3 py-1 text-sm rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        Priority: {{ ucfirst($priorityFilter) }}
                        <button wire:click="removePriorityFilter" class="hover:text-blue-900 dark:hover:text-blue-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endif

                <!-- Complexity Filter -->
                @if($complexityFilter)
                    <span class="inline-flex items-center gap-1 px-3 py-1 text-sm rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        Complexity: {{ ucfirst($complexityFilter) }}
                        <button wire:click="removeComplexityFilter" class="hover:text-blue-900 dark:hover:text-blue-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endif

                <!-- Project Filter -->
                @if($projectFilter)
                    @php
                        $project = $this->availableProjects->firstWhere('id', $projectFilter);
                    @endphp
                    @if($project)
                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                            Project: {{ $project->name }}
                            <button wire:click="removeProjectFilter" class="hover:text-blue-900 dark:hover:text-blue-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    @endif
                @endif

                <!-- Date Range Filter -->
                @if($dateRangeFilter)
                    <span class="inline-flex items-center gap-1 px-3 py-1 text-sm rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        Date: {{ match($dateRangeFilter) {
                            'today' => 'Today',
                            'this_week' => 'This Week',
                            'this_month' => 'This Month',
                            'overdue' => 'Overdue',
                            'custom' => 'Custom Range',
                            default => $dateRangeFilter,
                        } }}
                        <button wire:click="removeDateRangeFilter" class="hover:text-blue-900 dark:hover:text-blue-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endif

                <!-- Tag Filters -->
                @foreach($tagFiltersArray as $tagName)
                    <span class="inline-flex items-center gap-1 px-3 py-1 text-sm rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        {{ $tagName }}
                        <button wire:click="removeTagFilter('{{ $tagName }}')" class="hover:text-blue-900 dark:hover:text-blue-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endforeach
            </div>
        @endif

        <!-- Clear Filters Button -->
        @if($statusFilter || $priorityFilter || $complexityFilter || !empty($tagFiltersArray) || $projectFilter || $dateRangeFilter)
            <div class="mt-4">
                <flux:button variant="ghost" wire:click="clearFilters">
                    Clear All Filters
                </flux:button>
            </div>
        @endif
    </div>

    <!-- Bulk Actions Toolbar -->
    @if(!empty($selectedItems))
        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">
                    {{ count($selectedItems) }} item(s) selected
                </span>
                <div class="flex flex-wrap gap-2">
                    <!-- Change Status -->
                    <flux:dropdown>
                        <flux:button variant="primary" size="sm">
                            Change Status
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item wire:click="bulkChangeStatus('to_do')">To Do</flux:menu.item>
                            <flux:menu.item wire:click="bulkChangeStatus('doing')">In Progress</flux:menu.item>
                            <flux:menu.item wire:click="bulkChangeStatus('done')">Done</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    <!-- Change Priority (Tasks Only) -->
                    <flux:dropdown>
                        <flux:button variant="primary" size="sm">
                            Change Priority
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item wire:click="bulkChangePriority('low')">Low</flux:menu.item>
                            <flux:menu.item wire:click="bulkChangePriority('medium')">Medium</flux:menu.item>
                            <flux:menu.item wire:click="bulkChangePriority('high')">High</flux:menu.item>
                            <flux:menu.item wire:click="bulkChangePriority('urgent')">Urgent</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    <!-- Assign to Project (Tasks Only) -->
                    @if($this->availableProjects->isNotEmpty())
                        <flux:dropdown>
                            <flux:button variant="primary" size="sm">
                                Assign Project
                            </flux:button>
                            <flux:menu>
                                @foreach($this->availableProjects as $project)
                                    <flux:menu.item wire:click="bulkAssignProject({{ $project->id }})">{{ $project->name }}</flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    @endif

                    <!-- Delete -->
                    <flux:button
                        variant="danger"
                        size="sm"
                        wire:click="bulkDelete"
                        wire:confirm="Are you sure you want to delete {{ count($selectedItems) }} item(s)?"
                    >
                        Delete
                    </flux:button>

                    <flux:button variant="ghost" size="sm" wire:click="$set('selectedItems', [])">
                        Cancel
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    <!-- List View -->
    @if($viewMode === 'list')
        <div class="space-y-4">
            @forelse($this->items as $item)
                <div class="flex items-start gap-3">
                    <div class="pt-4">
                        <input
                            type="checkbox"
                            wire:model.live="selectedItems"
                            value="{{ $item->id }}-{{ $item->item_type }}"
                            class="w-4 h-4 text-blue-600 border-zinc-300 rounded focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex-1">
                        @if($item->item_type === 'task')
                            <x-workspace.task-card :task="$item" />
                        @elseif($item->item_type === 'event')
                            <x-workspace.event-card :event="$item" />
                        @elseif($item->item_type === 'project')
                            <x-workspace.project-card :project="$item" />
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">No items found</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        @if($search)
                            Try adjusting your search or filters.
                        @else
                            Get started by creating a new task, event, or project.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Kanban View -->
    @if($viewMode === 'kanban')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach(['to_do', 'doing', 'done'] as $status)
                <div class="bg-zinc-100 dark:bg-zinc-900 rounded-lg p-4"
                     x-data="{ draggingOver: false }"
                     @dragover.prevent="draggingOver = true"
                     @dragleave="draggingOver = false"
                     @drop.prevent="
                         draggingOver = false;
                         const taskId = $event.dataTransfer.getData('taskId');
                         const itemType = $event.dataTransfer.getData('itemType');
                         if (itemType === 'task') {
                             $wire.updateTaskStatus(parseInt(taskId), '{{ $status }}');
                         }
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
                                draggable="true"
                                @dragstart="
                                    $event.dataTransfer.effectAllowed = 'move';
                                    $event.dataTransfer.setData('taskId', {{ $item->id }});
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
                    <flux:button variant="ghost" size="sm" wire:click="openTimegridSettings" title="Timegrid Settings">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
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
                                     const minutes = Math.floor((totalMinutes % 60) / {{ $slotIncrement }}) * {{ $slotIncrement }};

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

                                <!-- Current Time Indicator -->
                                @if($isToday && now()->hour >= $startHour && now()->hour <= $endHour)
                                    @php
                                        $currentMinutes = (now()->hour - $startHour) * 60 + now()->minute;
                                        $currentTop = ($currentMinutes / 60) * $hourHeight;
                                    @endphp
                                    <div class="absolute w-full border-t-2 border-red-500 z-10" style="top: {{ $currentTop }}px;">
                                        <div class="absolute -left-1 -top-1 w-2 h-2 bg-red-500 rounded-full"></div>
                                    </div>
                                @endif

                                <!-- Timed Items -->
                                @foreach($timedItems as $item)
                                    <div
                                        draggable="true"
                                        @dragstart="
                                            $event.dataTransfer.effectAllowed = 'move';
                                            $event.dataTransfer.setData('itemId', {{ $item->id }});
                                            $event.dataTransfer.setData('itemType', '{{ $item->item_type }}');
                                            $event.dataTransfer.setData('duration', {{ $item->item_type === 'event' ? (Carbon::parse($item->end_datetime)->diffInMinutes(Carbon::parse($item->start_datetime))) : 60 }});
                                        "
                                        class="absolute w-full px-1 py-1 text-xs rounded cursor-move overflow-hidden hover:z-20 hover:shadow-lg transition-shadow"
                                        style="top: {{ $item->grid_top }}px; height: {{ $item->grid_height }}px; background-color: {{ $item->color ?? ($item->item_type === 'event' ? '#8b5cf6' : '#3b82f6') }}; color: white;"
                                        wire:click="$dispatch('view-{{ $item->item_type }}-detail', { id: {{ $item->id }} })"
                                    >
                                        <div class="font-semibold truncate">{{ $item->title }}</div>
                                        @if($item->item_type === 'event')
                                            <div class="text-xs opacity-90">
                                                {{ Carbon::parse($item->start_datetime)->format('g:i A') }}
                                            </div>
                                        @endif
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

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

    public function openCreateModal(): void
    {
        $this->dispatch('open-create-modal');
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
        // If not in list or kanban view, switch to list view (e.g., when clicking calendar day)
        if (! in_array($this->viewMode, ['list', 'kanban'])) {
            $this->viewMode = 'list';
        }
        $this->currentDate = Carbon::parse($date);
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
                $this->dispatch('item-updated');
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
            }
        }
        // Projects don't have status, so no update needed
    }

    #[Computed]
    public function items(): Collection
    {
        $user = auth()->user();
        $items = collect();

        // Always fetch all tasks
        $tasks = Task::query()
            ->where('user_id', $user->id)
            ->with(['project', 'tags', 'event'])
            ->get()
            ->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_date ?? $task->created_at;

                return $task;
            });

        // Always fetch all events
        $events = Event::query()
            ->where('user_id', $user->id)
            ->with(['tags'])
            ->get()
            ->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime;

                return $event;
            });

        // Always fetch all projects
        $projects = Project::query()
            ->where('user_id', $user->id)
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
        <livewire:workspace.list-view
            :items="$this->items"
            :current-date="$currentDate"
            wire:key="list-view-{{ $currentDate->format('Y-m-d') }}"
        />
    @endif

    <!-- Kanban View -->
    @if($viewMode === 'kanban')
        <livewire:workspace.kanban-view
            :items="$this->items"
            :items-by-status="$this->itemsByStatus"
            :current-date="$currentDate"
            wire:key="kanban-view-{{ $currentDate->format('Y-m-d') }}"
        />
    @endif

    <!-- Weekly Timegrid View -->
    @if($viewMode === 'weekly')
        <livewire:workspace.weekly-view
            :items="$this->items"
            :week-start-date="$weekStartDate"
            wire:key="weekly-view-{{ $weekStartDate->format('Y-m-d') }}"
        />
    @endif

    <!-- Floating Action Button -->
    <div class="fixed bottom-8 right-8">
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Create
        </flux:button>
    </div>
</div>

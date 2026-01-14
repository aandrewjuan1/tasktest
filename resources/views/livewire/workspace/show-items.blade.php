<?php

use App\Services\EventService;
use App\Services\ProjectService;
use App\Services\TagService;
use App\Services\TaskService;
use App\Traits\FiltersItems;
use App\Traits\HandlesDateRanges;
use App\Traits\HandlesWorkspaceItems;
use App\Traits\HandlesWorkspaceTags;
use App\Traits\SortsItems;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use FiltersItems, HandlesDateRanges, HandlesWorkspaceItems, HandlesWorkspaceTags, SortsItems, WithPagination;

    protected ?TaskService $taskService = null;

    protected ?EventService $eventService = null;

    protected ?ProjectService $projectService = null;

    protected ?TagService $tagService = null;

    // View and display options
    #[Url(except: 'list')]
    public string $viewMode = 'list'; // list, kanban, daily-timegrid, weekly-timegrid

    // Timegrid view properties
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

    public array $filterTagIds = [];

    // Sort properties
    #[Url]
    public ?string $sortBy = null;

    #[Url]
    public string $sortDirection = 'asc';

    public function mount(
        TaskService $taskService,
        EventService $eventService,
        ProjectService $projectService,
        TagService $tagService,
    ): void {
        $this->taskService = $taskService;
        $this->eventService = $eventService;
        $this->projectService = $projectService;
        $this->tagService = $tagService;

        $this->weekStartDate = now()->startOfWeek();
        $this->currentDate = now();
    }

    protected function getTaskService(): TaskService
    {
        return $this->taskService ??= app(TaskService::class);
    }

    protected function getEventService(): EventService
    {
        return $this->eventService ??= app(EventService::class);
    }

    protected function getProjectService(): ProjectService
    {
        return $this->projectService ??= app(ProjectService::class);
    }

    protected function getTagService(): TagService
    {
        return $this->tagService ??= app(TagService::class);
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
        // Reset filters and sorts when switching views
        $this->clearAll();
    }

    public function goToTodayDate(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban', 'daily-timegrid'])) {
            $this->currentDate = now()->startOfDay();
        }
    }

    public function previousDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban', 'daily-timegrid']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->startOfDay()->subDay();
        }
    }

    public function nextDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban', 'daily-timegrid']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->startOfDay()->addDay();
        }
    }

    #[On('switch-to-daily-timegrid')]
    public function switchToDailyTimegrid(?string $date = null): void
    {
        $this->viewMode = 'daily-timegrid';
        if ($date) {
            $this->currentDate = Carbon::parse($date);
        } else {
            $this->currentDate = $this->currentDate ?? now();
        }
    }

    #[On('switch-to-weekly-timegrid')]
    public function switchToWeeklyTimegrid(?string $weekStart = null): void
    {
        $this->viewMode = 'weekly-timegrid';
        if ($weekStart) {
            $this->weekStartDate = Carbon::parse($weekStart);
        } else {
            $this->weekStartDate = $this->weekStartDate ?? now()->startOfWeek();
        }
    }

    #[On('switch-to-timegrid-day')]
    public function switchToTimegridDay(string $date): void
    {
        // Legacy support - redirect to daily timegrid
        $this->switchToDailyTimegrid($date);
    }

    #[On('switch-to-timegrid-week')]
    public function switchToTimegridWeek(string $weekStart): void
    {
        // Legacy support - redirect to weekly timegrid
        $this->switchToWeeklyTimegrid($weekStart);
    }

    #[On('switch-to-week-view')]
    public function switchToWeekView(string $weekStart): void
    {
        // Legacy support - redirect to weekly timegrid
        $this->switchToWeeklyTimegrid($weekStart);
    }

    #[On('update-item-status')]
    public function handleUpdateItemStatus(int|string $itemId, string $itemType, string $newStatus, ?string $instanceDate = null): void
    {
        $this->updateItemStatus($itemId, $itemType, $newStatus, $instanceDate);
    }

    #[On('update-item-datetime')]
    public function handleUpdateItemDateTime(int|string $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        $this->updateItemDateTime($itemId, $itemType, $newStart, $newEnd);
    }

    #[On('update-item-duration')]
    public function handleUpdateItemDuration(int|string $itemId, string $itemType, int $newDurationMinutes): void
    {
        $this->updateItemDuration($itemId, $itemType, $newDurationMinutes);
    }

    #[On('delete-task')]
    public function handleDeleteTask(int|string $taskId): void
    {
        $this->deleteTask($taskId);
    }

    #[On('add-task-comment')]
    public function handleAddTaskComment($taskId = null, $content = null): void
    {
        if ($taskId === null || $content === null) {
            return;
        }

        $this->addTaskComment($taskId, (string) $content);
    }

    #[On('update-task-comment')]
    public function handleUpdateTaskComment($taskId = null, $commentId = null, $content = null): void
    {
        if ($taskId === null || $commentId === null || $content === null) {
            return;
        }

        $this->updateTaskComment($taskId, (int) $commentId, (string) $content);
    }

    #[On('delete-task-comment')]
    public function handleDeleteTaskComment($taskId = null, $commentId = null): void
    {
        if ($taskId === null || $commentId === null) {
            return;
        }

        $this->deleteTaskComment($taskId, (int) $commentId);
    }


    #[On('reset-filters-sorts')]
    public function resetFiltersAndSorts(): void
    {
        $this->clearAll();
    }

    #[On('set-filter-type')]
    public function handleSetFilterType(?string $type = null): void
    {
        $this->setFilterType($type);
    }

    #[On('set-filter-priority')]
    public function handleSetFilterPriority(?string $priority = null): void
    {
        $this->setFilterPriority($priority);
    }

    #[On('set-filter-status')]
    public function handleSetFilterStatus(?string $status = null): void
    {
        $this->setFilterStatus($status);
    }

    #[On('set-sort-by')]
    public function handleSetSortBy(?string $sortBy = null): void
    {
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
        $this->filterTagIds = [];
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

    #[On('update-task-field')]
    public function handleUpdateTaskField(int|string $taskId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        $this->updateTaskField($taskId, $field, $value, $instanceDate);
    }

    #[On('update-task-tags')]
    public function handleUpdateTaskTags(int $itemId, string $itemType, array $tagIds): void
    {
        $this->updateTaskTags($itemId, $itemType, $tagIds);
    }

    #[On('update-event-field')]
    public function handleUpdateEventField(int|string $eventId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        $this->updateEventField($eventId, $field, $value, $instanceDate);
    }

    #[On('delete-event')]
    public function handleDeleteEvent(int|string $eventId): void
    {
        $this->deleteEvent($eventId);
    }

    #[On('update-project-field')]
    public function handleUpdateProjectField(int $projectId, string $field, mixed $value): void
    {
        $this->updateProjectField($projectId, $field, $value);
    }

    #[On('delete-project')]
    public function handleDeleteProject(int $projectId): void
    {
        $this->deleteProject($projectId);
    }

}; ?>
<div
    wire:key="show-items-component"
    class="space-y-4 px-4"
    wire:loading.class="opacity-50"
    wire:target="switchView,goToTodayDate,previousDay,nextDay"
    x-data="{
        tagDropdown: {
            showCreateInput: false,
            newTagName: '',
            creating: false,
            deleting: false,
            async handleCreate() {
                if (!this.newTagName.trim() || this.creating) return;
                this.creating = true;
                const name = this.newTagName.trim();
                const response = await $wire.createTag(name);
                this.creating = false;
                if (response?.success) {
                    this.newTagName = '';
                    this.showCreateInput = false;
                }
            },
            async handleDelete(tagId) {
                if (this.deleting) return;
                this.deleting = true;
                const response = await $wire.deleteTag(tagId);
                this.deleting = false;
                if (!response?.success) return;
            }
        }
    }"
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
        wire:target="goToTodayDate,previousDay,nextDay"
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
        <div wire:key="list-view-container-{{ ($currentDate ?? now())->format('Y-m-d') }}">
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
                wire:key="list-view-{{ $viewMode }}-{{ ($currentDate ?? now())->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Kanban View -->
    @if($viewMode === 'kanban')
        <div wire:key="kanban-view-container-{{ ($currentDate ?? now())->format('Y-m-d') }}">
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
                wire:key="kanban-view-{{ $viewMode }}-{{ ($currentDate ?? now())->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Daily Timegrid View -->
    @if($viewMode === 'daily-timegrid')
        <div wire:key="daily-timegrid-view-container-{{ ($currentDate ?? now())->format('Y-m-d') }}">
            <livewire:workspace.daily-timegrid
                :items="$this->filteredItems"
                :current-date="$currentDate"
                :view-mode="$viewMode"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                wire:key="daily-timegrid-view-{{ ($currentDate ?? now())->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Weekly Timegrid View -->
    @if($viewMode === 'weekly-timegrid')
        <div wire:key="weekly-timegrid-view-container-{{ ($weekStartDate ?? now()->startOfWeek())->format('Y-m-d') }}">
            <livewire:workspace.weekly-timegrid
                :items="$this->filteredItems"
                :week-start-date="$weekStartDate"
                :view-mode="$viewMode"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                wire:key="weekly-timegrid-view-{{ ($weekStartDate ?? now()->startOfWeek())->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Create Item -->
    <livewire:workspace.create-item />
</div>

<?php

use App\Enums\EventStatus;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public string $activeTab = 'task';

    // Task fields
    public string $taskTitle = '';
    public ?string $taskStatus = 'to_do';
    public ?string $taskPriority = 'medium';
    public ?string $taskComplexity = 'moderate';
    public ?int $taskDuration = null;
    public ?string $taskStartDate = null;
    public ?string $taskStartTime = null;
    public ?string $taskEndDate = null;
    public ?int $taskProjectId = null;
    public array $taskTagIds = [];

    // Event fields
    public string $eventTitle = '';
    public ?string $eventStatus = 'scheduled';
    public ?string $eventStartDatetime = null;
    public ?string $eventEndDatetime = null;
    public ?string $eventLocation = null;
    public ?string $eventColor = null;
    public array $eventTagIds = [];

    // Project fields
    public string $projectName = '';
    public ?string $projectStartDate = null;
    public ?string $projectEndDate = null;
    public array $projectTagIds = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->activeTab = 'task';
        $this->taskTitle = '';
        $this->taskStatus = 'to_do';
        $this->taskPriority = 'medium';
        $this->taskComplexity = 'moderate';
        $this->taskDuration = null;
        $this->taskStartDate = null;
        $this->taskStartTime = null;
        $this->taskEndDate = null;
        $this->taskProjectId = null;
        $this->taskTagIds = [];

        $this->eventTitle = '';
        $this->eventStatus = 'scheduled';
        $this->eventStartDatetime = null;
        $this->eventEndDatetime = null;
        $this->eventLocation = null;
        $this->eventColor = null;
        $this->eventTagIds = [];

        $this->projectName = '';
        $this->projectStartDate = null;
        $this->projectEndDate = null;
        $this->projectTagIds = [];
    }

    public function createTask(): void
    {
        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskStatus' => ['nullable', 'string', 'in:to_do,doing,done'],
            'taskPriority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'taskComplexity' => ['nullable', 'string', 'in:simple,moderate,complex'],
            'taskDuration' => ['nullable', 'integer', 'min:1'],
            'taskStartDate' => ['nullable', 'date'],
            'taskStartTime' => ['nullable', 'date_format:H:i'],
            'taskEndDate' => ['nullable', 'date', 'after_or_equal:taskStartDate'],
            'taskProjectId' => ['nullable', 'exists:projects,id'],
            'taskTagIds' => ['nullable', 'array'],
            'taskTagIds.*' => ['exists:tags,id'],
        ], [], [
            'taskTitle' => 'title',
            'taskStatus' => 'status',
            'taskPriority' => 'priority',
            'taskComplexity' => 'complexity',
            'taskDuration' => 'duration',
            'taskStartDate' => 'start date',
            'taskStartTime' => 'start time',
            'taskEndDate' => 'end date',
            'taskProjectId' => 'project',
        ]);

        $task = Task::create([
            'user_id' => auth()->id(),
            'title' => $validated['taskTitle'],
            'status' => $validated['taskStatus'] ? TaskStatus::from($validated['taskStatus']) : null,
            'priority' => $validated['taskPriority'] ? TaskPriority::from($validated['taskPriority']) : null,
            'complexity' => $validated['taskComplexity'] ? TaskComplexity::from($validated['taskComplexity']) : null,
            'duration' => $validated['taskDuration'] ?? null,
            'start_date' => $validated['taskStartDate'] ?? null,
            'start_time' => $validated['taskStartTime'] ?? null,
            'end_date' => $validated['taskEndDate'] ?? null,
            'project_id' => $validated['taskProjectId'] ?? null,
        ]);

        if (!empty($validated['taskTagIds'])) {
            $task->tags()->attach($validated['taskTagIds']);
        }

        $this->dispatch('item-updated');
        $this->dispatch('notify', message: 'Task created successfully', type: 'success');
        $this->dispatch('close-create-modal');
    }

    public function createEvent(): void
    {
        $validated = $this->validate([
            'eventTitle' => ['required', 'string', 'max:255'],
            'eventStatus' => ['nullable', 'string', 'in:scheduled,cancelled,completed,tentative'],
            'eventStartDatetime' => ['nullable', 'date'],
            'eventEndDatetime' => ['nullable', 'date', 'after:eventStartDatetime'],
            'eventLocation' => ['nullable', 'string', 'max:255'],
            'eventColor' => ['nullable', 'string', 'max:7'],
            'eventTagIds' => ['nullable', 'array'],
            'eventTagIds.*' => ['exists:tags,id'],
        ], [], [
            'eventTitle' => 'title',
            'eventStatus' => 'status',
            'eventStartDatetime' => 'start datetime',
            'eventEndDatetime' => 'end datetime',
            'eventLocation' => 'location',
            'eventColor' => 'color',
        ]);

        $startDatetime = $validated['eventStartDatetime'] ? Carbon::parse($validated['eventStartDatetime']) : null;
        $endDatetime = $validated['eventEndDatetime'] ? Carbon::parse($validated['eventEndDatetime']) : null;

        $event = Event::create([
            'user_id' => auth()->id(),
            'title' => $validated['eventTitle'],
            'status' => $validated['eventStatus'] ? EventStatus::from($validated['eventStatus']) : null,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'location' => $validated['eventLocation'] ?? null,
            'color' => $validated['eventColor'] ?? null,
            'timezone' => config('app.timezone'),
        ]);

        if (!empty($validated['eventTagIds'])) {
            $event->tags()->attach($validated['eventTagIds']);
        }

        $this->dispatch('item-updated');
        $this->dispatch('notify', message: 'Event created successfully', type: 'success');
        $this->dispatch('close-create-modal');
    }

    public function createProject(): void
    {
        $validated = $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectStartDate' => ['nullable', 'date'],
            'projectEndDate' => ['nullable', 'date', 'after_or_equal:projectStartDate'],
            'projectTagIds' => ['nullable', 'array'],
            'projectTagIds.*' => ['exists:tags,id'],
        ], [], [
            'projectName' => 'name',
            'projectStartDate' => 'start date',
            'projectEndDate' => 'end date',
        ]);

        $project = Project::create([
            'user_id' => auth()->id(),
            'name' => $validated['projectName'],
            'start_date' => $validated['projectStartDate'] ?? null,
            'end_date' => $validated['projectEndDate'] ?? null,
        ]);

        if (!empty($validated['projectTagIds'])) {
            $project->tags()->attach($validated['projectTagIds']);
        }

        $this->dispatch('item-updated');
        $this->dispatch('notify', message: 'Project created successfully', type: 'success');
        $this->dispatch('close-create-modal');
    }

    #[Computed]
    public function availableProjects(): Collection
    {
        return Project::accessibleBy(auth()->user())
            ->orderBy('name')
            ->get();
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
}; ?>

<div x-data="{
    activeTab: 'task',
    openDropdown: null,
    openModal() {
        this.activeTab = 'task';
        this.openDropdown = null;
        $flux.modal('create-item').show();
    },
    closeModal() {
        $flux.modal('create-item').close();
        this.openDropdown = null;
        $wire.call('resetForm');
        $wire.resetValidation();
    },
    switchTab(tab) {
        this.activeTab = tab;
        this.openDropdown = null;
    },
    toggleDropdown(id) {
        this.openDropdown = this.openDropdown === id ? null : id;
    },
    isOpen(id) {
        return this.openDropdown === id;
    }
}"
     @open-create-modal.window="openModal()"
     @close-create-modal.window="closeModal()">
    <flux:modal name="create-item" class="max-w-2xl">
        <div class="space-y-6">
            <!-- Tabs -->
            <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
                <button
                    @click="switchTab('task')"
                    :class="activeTab === 'task' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                    class="px-4 py-2 font-medium text-sm transition-colors border-b-2"
                >
                    Task
                </button>
                <button
                    @click="switchTab('event')"
                    :class="activeTab === 'event' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                    class="px-4 py-2 font-medium text-sm transition-colors border-b-2"
                >
                    Event
                </button>
                <button
                    @click="switchTab('project')"
                    :class="activeTab === 'project' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                    class="px-4 py-2 font-medium text-sm transition-colors border-b-2"
                >
                    Project
                </button>
            </div>

            <!-- Title Input -->
            <div>
                <div x-show="activeTab === 'task'">
                    <flux:input wire:model="taskTitle" label="Title" placeholder="Enter task title" required />
                </div>
                <div x-show="activeTab === 'event'">
                    <flux:input wire:model="eventTitle" label="Title" placeholder="Enter event title" required />
                </div>
                <div x-show="activeTab === 'project'">
                    <flux:input wire:model="projectName" label="Name" placeholder="Enter project name" required />
                </div>
            </div>

            <!-- Property Buttons -->
            <div class="flex flex-wrap gap-2">
                <template x-if="activeTab === 'task'">
                    <div class="contents">
                    <!-- Task Status -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('task-status')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                            <span class="text-sm font-medium">Status</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ match($taskStatus) {
                                    'to_do' => 'To Do',
                                    'doing' => 'In Progress',
                                    'done' => 'Done',
                                    default => 'To Do'
                                } }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen('task-status')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                wire:click="$set('taskStatus', 'to_do'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskStatus === 'to_do' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                To Do
                            </button>
                            <button
                                wire:click="$set('taskStatus', 'doing'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskStatus === 'doing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                In Progress
                            </button>
                            <button
                                wire:click="$set('taskStatus', 'done'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskStatus === 'done' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Done
                            </button>
                        </div>
                    </div>

                    <!-- Task Priority -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('task-priority')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span class="text-sm font-medium">Priority</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize">{{ $taskPriority ?? 'Medium' }}</span>
                        </button>
                        <div
                            x-show="isOpen('task-priority')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                wire:click="$set('taskPriority', 'low'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskPriority === 'low' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Low
                            </button>
                            <button
                                wire:click="$set('taskPriority', 'medium'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskPriority === 'medium' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Medium
                            </button>
                            <button
                                wire:click="$set('taskPriority', 'high'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskPriority === 'high' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                High
                            </button>
                            <button
                                wire:click="$set('taskPriority', 'urgent'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskPriority === 'urgent' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Urgent
                            </button>
                        </div>
                    </div>

                    <!-- Task Complexity -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('task-complexity')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span class="text-sm font-medium">Complexity</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize">{{ $taskComplexity ?? 'Moderate' }}</span>
                        </button>
                        <div
                            x-show="isOpen('task-complexity')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                wire:click="$set('taskComplexity', 'simple'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskComplexity === 'simple' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Simple
                            </button>
                            <button
                                wire:click="$set('taskComplexity', 'moderate'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskComplexity === 'moderate' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Moderate
                            </button>
                            <button
                                wire:click="$set('taskComplexity', 'complex'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskComplexity === 'complex' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Complex
                            </button>
                        </div>
                    </div>

                    <!-- Task Duration -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('task-duration')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium">Duration</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $taskDuration ? $taskDuration . ' min' : 'Not set' }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen('task-duration')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                            @click.away="openDropdown = null"
                        >
                            @foreach([15, 30, 45, 60, 90, 120, 180, 240, 300] as $minutes)
                                <button
                                    wire:click="$set('taskDuration', {{ $minutes }}); openDropdown = null"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskDuration === $minutes ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                                >
                                    {{ $minutes }} minutes
                                </button>
                            @endforeach
                            <button
                                wire:click="$set('taskDuration', null); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskDuration === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Task Dates -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('task-dates')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">Dates</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $taskStartDate || $taskEndDate ? 'Set' : 'Not set' }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen('task-dates')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-4"
                            @click.away="openDropdown = null"
                        >
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Start Date</label>
                                    <flux:input wire:model="taskStartDate" type="date" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Start Time</label>
                                    <flux:input wire:model="taskStartTime" type="time" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">End Date</label>
                                    <flux:input wire:model="taskEndDate" type="date" />
                                </div>
                                <button
                                    wire:click="$set('taskStartDate', null); $set('taskStartTime', null); $set('taskEndDate', null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear all
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Task Project -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('task-project')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <span class="text-sm font-medium">Project</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $taskProjectId ? 'Selected' : 'None' }}
                            </span>
                        </button>
                        <template x-if="isOpen('task-project')">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="openDropdown = null"
                            >
                                <button
                                    wire:click="$set('taskProjectId', null); openDropdown = null"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskProjectId === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                                >
                                    None
                                </button>
                                @foreach($this->availableProjects as $project)
                                <button
                                    wire:click="$set('taskProjectId', {{ $project->id }}); openDropdown = null"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $taskProjectId === $project->id ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                                >
                                    {{ $project->name }}
                                </button>
                                @endforeach
                            </div>
                        </template>
                    </div>

                    <!-- Task Tags -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('task-tags')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span class="text-sm font-medium">Tags</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ count($taskTagIds) > 0 ? count($taskTagIds) . ' selected' : 'None' }}
                            </span>
                        </button>
                        <template x-if="open">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="open = false"
                            >
                                @foreach($this->availableTags as $tag)
                                    <label class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="taskTagIds"
                                            value="{{ $tag->id }}"
                                            class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="ml-2 text-sm">{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                                @if($this->availableTags->isEmpty())
                                    <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
                                @endif
                            </div>
                        </template>
                    </div>
                    </div>
                </template>
                <template x-if="activeTab === 'event'">
                    <div class="contents">
                    <!-- Event Status -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('event-status')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                            <span class="text-sm font-medium">Status</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize">{{ $eventStatus ?? 'Scheduled' }}</span>
                        </button>
                        <div
                            x-show="isOpen('event-status')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                wire:click="$set('eventStatus', 'scheduled'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $eventStatus === 'scheduled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Scheduled
                            </button>
                            <button
                                wire:click="$set('eventStatus', 'tentative'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $eventStatus === 'tentative' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Tentative
                            </button>
                            <button
                                wire:click="$set('eventStatus', 'completed'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $eventStatus === 'completed' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Completed
                            </button>
                            <button
                                wire:click="$set('eventStatus', 'cancelled'); openDropdown = null"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $eventStatus === 'cancelled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Cancelled
                            </button>
                        </div>
                    </div>

                    <!-- Event Dates -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('event-dates')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">Dates</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $eventStartDatetime || $eventEndDatetime ? 'Set' : 'Not set' }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen('event-dates')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-4"
                            @click.away="openDropdown = null"
                        >
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Start Date & Time</label>
                                    <flux:input wire:model="eventStartDatetime" type="datetime-local" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">End Date & Time</label>
                                    <flux:input wire:model="eventEndDatetime" type="datetime-local" />
                                </div>
                                <button
                                    wire:click="$set('eventStartDatetime', null); $set('eventEndDatetime', null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear all
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Event Location -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('event-location')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-sm font-medium">Location</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $eventLocation ? Str::limit($eventLocation, 15) : 'Not set' }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen('event-location')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-4"
                            @click.away="openDropdown = null"
                        >
                            <flux:input wire:model="eventLocation" placeholder="Enter location" />
                        </div>
                    </div>

                    <!-- Event Color -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('event-color')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            <span class="text-sm font-medium">Color</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $eventColor ? 'Set' : 'Not set' }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen('event-color')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-4"
                            @click.away="openDropdown = null"
                        >
                            <div class="grid grid-cols-6 gap-2">
                                @foreach(['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#14b8a6', '#64748b'] as $color)
                                    <button
                                        wire:click="$set('eventColor', '{{ $color }}'); openDropdown = null"
                                        class="w-8 h-8 rounded border-2 {{ $eventColor === $color ? 'border-zinc-900 dark:border-zinc-100 ring-2 ring-offset-2' : 'border-zinc-300 dark:border-zinc-600' }}"
                                        style="background-color: {{ $color }}"
                                    ></button>
                                @endforeach
                            </div>
                            <button
                                wire:click="$set('eventColor', null); openDropdown = null"
                                class="mt-2 w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Event Tags -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('event-tags')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span class="text-sm font-medium">Tags</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ count($eventTagIds) > 0 ? count($eventTagIds) . ' selected' : 'None' }}
                            </span>
                        </button>
                        <template x-if="isOpen('event-tags')">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="openDropdown = null"
                            >
                                @foreach($this->availableTags as $tag)
                                    <label class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="eventTagIds"
                                            value="{{ $tag->id }}"
                                            class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="ml-2 text-sm">{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                                @if($this->availableTags->isEmpty())
                                    <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
                                @endif
                            </div>
                        </template>
                    </div>
                    </div>
                </template>
                <template x-if="activeTab === 'project'">
                    <div class="contents">
                        <!-- Project Dates -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('project-dates')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">Dates</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $projectStartDate || $projectEndDate ? 'Set' : 'Not set' }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen('project-dates')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-4"
                            @click.away="openDropdown = null"
                        >
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Start Date</label>
                                    <flux:input wire:model="projectStartDate" type="date" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">End Date</label>
                                    <flux:input wire:model="projectEndDate" type="date" />
                                </div>
                                <button
                                    wire:click="$set('projectStartDate', null); $set('projectEndDate', null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear all
                                </button>
                            </div>
                        </div>
                    </div>

                        <!-- Project Tags -->
                    <div class="relative" @click.away="openDropdown = null">
                        <button
                            type="button"
                            @click.stop="toggleDropdown('project-tags')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span class="text-sm font-medium">Tags</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ count($projectTagIds) > 0 ? count($projectTagIds) . ' selected' : 'None' }}
                            </span>
                        </button>
                        <template x-if="isOpen('project-tags')">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="openDropdown = null"
                            >
                                @foreach($this->availableTags as $tag)
                                    <label class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="projectTagIds"
                                            value="{{ $tag->id }}"
                                            class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="ml-2 text-sm">{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                                @if($this->availableTags->isEmpty())
                                    <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
                                @endif
                            </div>
                        </template>
                    </div>
                    </div>
                </template>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" @click="$flux.modal('create-item').close(); $wire.call('resetForm'); $wire.resetValidation();">
                    Cancel
                </flux:button>
                <flux:button x-show="activeTab === 'task'" variant="primary" wire:click="createTask">
                    Create Task
                </flux:button>
                <flux:button x-show="activeTab === 'event'" variant="primary" wire:click="createEvent">
                    Create Event
                </flux:button>
                <flux:button x-show="activeTab === 'project'" variant="primary" wire:click="createProject">
                    Create Project
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

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
use Illuminate\Support\Facades\DB;
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
    public ?int $taskDuration = 60;
    public ?string $taskStartDatetime = null;
    public ?string $taskEndDatetime = null;
    public ?int $taskProjectId = null;
    public array $taskTagIds = [];

    // Event fields
    public string $eventTitle = '';
    public ?string $eventStatus = 'scheduled';
    public ?string $eventStartDatetime = null;
    public ?string $eventEndDatetime = null;
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
        $this->taskDuration = 60;
        $this->taskStartDatetime = Carbon::now()->format('Y-m-d\TH:i');
        $this->taskEndDatetime = null;
        $this->taskProjectId = null;
        $this->taskTagIds = [];

        $this->eventTitle = '';
        $this->eventStatus = 'scheduled';
        $this->eventStartDatetime = Carbon::now()->format('Y-m-d\TH:i');
        $this->eventEndDatetime = null;
        $this->eventTagIds = [];

        $this->projectName = '';
        $this->projectStartDate = Carbon::today()->toDateString();
        $this->projectEndDate = null;
        $this->projectTagIds = [];

        $this->resetValidation();
    }

    public function createTask(): void
    {
        $this->authorize('create', Task::class);

        try {
            $validated = $this->validate([
                'taskTitle' => ['required', 'string', 'max:255'],
                'taskStatus' => ['nullable', 'string', 'in:to_do,doing,done'],
                'taskPriority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
                'taskComplexity' => ['nullable', 'string', 'in:simple,moderate,complex'],
                'taskDuration' => ['nullable', 'integer', 'min:1'],
                'taskStartDatetime' => ['nullable', 'date'],
                'taskEndDatetime' => ['nullable', 'date', 'after_or_equal:taskStartDatetime'],
                'taskProjectId' => ['nullable', 'exists:projects,id'],
                'taskTagIds' => ['nullable', 'array'],
                'taskTagIds.*' => ['exists:tags,id'],
            ], [], [
                'taskTitle' => 'title',
                'taskStatus' => 'status',
                'taskPriority' => 'priority',
                'taskComplexity' => 'complexity',
                'taskDuration' => 'duration',
                'taskStartDatetime' => 'start datetime',
                'taskEndDatetime' => 'end datetime',
                'taskProjectId' => 'project',
            ]);

            $startDatetime = $validated['taskStartDatetime'] ? Carbon::parse($validated['taskStartDatetime']) : null;
            $endDatetime = $validated['taskEndDatetime'] ? Carbon::parse($validated['taskEndDatetime']) : null;

            DB::transaction(function () use ($validated, $startDatetime, $endDatetime) {
                $task = Task::create([
                    'user_id' => auth()->id(),
                    'title' => $validated['taskTitle'],
                    'status' => $validated['taskStatus'] ? TaskStatus::from($validated['taskStatus']) : null,
                    'priority' => $validated['taskPriority'] ? TaskPriority::from($validated['taskPriority']) : null,
                    'complexity' => $validated['taskComplexity'] ? TaskComplexity::from($validated['taskComplexity']) : null,
                    'duration' => $validated['taskDuration'] ?? null,
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                    'project_id' => $validated['taskProjectId'] ?? null,
                ]);

                if (!empty($validated['taskTagIds'])) {
                    $task->tags()->attach($validated['taskTagIds']);
                }
            });

            $this->resetForm();
            $this->dispatch('notify', message: 'Task created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create task', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to create task. Please try again.', type: 'error');
        }
    }

    public function createEvent(): void
    {
        $this->authorize('create', Event::class);

        try {
            $validated = $this->validate([
                'eventTitle' => ['required', 'string', 'max:255'],
                'eventStatus' => ['nullable', 'string', 'in:scheduled,cancelled,completed,tentative,ongoing'],
                'eventStartDatetime' => ['nullable', 'date'],
                'eventEndDatetime' => ['nullable', 'date', 'after:eventStartDatetime'],
                'eventTagIds' => ['nullable', 'array'],
                'eventTagIds.*' => ['exists:tags,id'],
            ], [], [
                'eventTitle' => 'title',
                'eventStatus' => 'status',
                'eventStartDatetime' => 'start datetime',
                'eventEndDatetime' => 'end datetime',
            ]);

            $startDatetime = $validated['eventStartDatetime'] ? Carbon::parse($validated['eventStartDatetime']) : null;
            $endDatetime = $validated['eventEndDatetime'] ? Carbon::parse($validated['eventEndDatetime']) : null;

            DB::transaction(function () use ($validated, $startDatetime, $endDatetime) {
                $event = Event::create([
                    'user_id' => auth()->id(),
                    'title' => $validated['eventTitle'],
                    'status' => $validated['eventStatus'] ? EventStatus::from($validated['eventStatus']) : null,
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                ]);

                if (!empty($validated['eventTagIds'])) {
                    $event->tags()->attach($validated['eventTagIds']);
                }
            });

            $this->resetForm();
            $this->dispatch('notify', message: 'Event created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to create event. Please try again.', type: 'error');
        }
    }

    public function createProject(): void
    {
        $this->authorize('create', Project::class);

        try {
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

            DB::transaction(function () use ($validated) {
                $project = Project::create([
                    'user_id' => auth()->id(),
                    'name' => $validated['projectName'],
                    'start_date' => $validated['projectStartDate'] ?? null,
                    'end_date' => $validated['projectEndDate'] ?? null,
                ]);

                if (!empty($validated['projectTagIds'])) {
                    $project->tags()->attach($validated['projectTagIds']);
                }
            });

            $this->resetForm();
            $this->dispatch('notify', message: 'Project created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to create project. Please try again.', type: 'error');
        }
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
    isOpen: false,
    formData: {
        task: {
            title: '',
            status: 'to_do',
            priority: 'medium',
            complexity: 'moderate',
            duration: 60,
            startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
            endDatetime: null,
            projectId: null,
            tagIds: []
        },
        event: {
            title: '',
            status: 'scheduled',
            startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
            endDatetime: null,
            tagIds: []
        },
        project: {
            name: '',
            startDate: '{{ Carbon::today()->toDateString() }}',
            endDate: null,
            tagIds: []
        }
    },
    openModal() {
        this.activeTab = 'task';
        this.isOpen = true;
        document.body.style.overflow = 'hidden';
    },
    closeModal() {
        this.isOpen = false;
        document.body.style.overflow = '';
    },
    switchTab(tab) {
        this.activeTab = tab;
    },
    get inputValue() {
        if (this.activeTab === 'task') {
            return this.formData.task.title;
        } else if (this.activeTab === 'event') {
            return this.formData.event.title;
        } else {
            return this.formData.project.name;
        }
    },
    set inputValue(value) {
        if (this.activeTab === 'task') {
            this.formData.task.title = value;
        } else if (this.activeTab === 'event') {
            this.formData.event.title = value;
        } else {
            this.formData.project.name = value;
        }
    },
    resetFormData() {
        this.formData = {
            task: {
                title: '',
                status: 'to_do',
                priority: 'medium',
                complexity: 'moderate',
                duration: 60,
                startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
                endDatetime: null,
                projectId: null,
                tagIds: []
            },
            event: {
                title: '',
                status: 'scheduled',
                startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
                endDatetime: null,
                location: null,
                color: null,
                tagIds: []
            },
            project: {
                name: '',
                startDate: '{{ Carbon::today()->toDateString() }}',
                endDate: null,
                tagIds: []
            }
        };
    },
    syncToLivewire() {
        // Directly set properties on $wire object to avoid multiple requests
        $wire.taskTitle = this.formData.task.title;
        $wire.taskStatus = this.formData.task.status;
        $wire.taskPriority = this.formData.task.priority;
        $wire.taskComplexity = this.formData.task.complexity;
        $wire.taskDuration = this.formData.task.duration;
        $wire.taskStartDatetime = this.formData.task.startDatetime;
        $wire.taskEndDatetime = this.formData.task.endDatetime;
        $wire.taskProjectId = this.formData.task.projectId;
        $wire.taskTagIds = this.formData.task.tagIds;

        $wire.eventTitle = this.formData.event.title;
        $wire.eventStatus = this.formData.event.status;
        $wire.eventStartDatetime = this.formData.event.startDatetime;
        $wire.eventEndDatetime = this.formData.event.endDatetime;
        $wire.eventTagIds = this.formData.event.tagIds;

        $wire.projectName = this.formData.project.name;
        $wire.projectStartDate = this.formData.project.startDate;
        $wire.projectEndDate = this.formData.project.endDate;
        $wire.projectTagIds = this.formData.project.tagIds;
    },
    submitTask() {
        this.syncToLivewire();
        $wire.createTask();
    },
    submitEvent() {
        this.syncToLivewire();
        $wire.createEvent();
    },
    submitProject() {
        this.syncToLivewire();
        $wire.createProject();
    },
    toggleTag(tagId, type) {
        const tagIds = this.formData[type].tagIds;
        const index = tagIds.indexOf(tagId);
        if (index > -1) {
            tagIds.splice(index, 1);
        } else {
            tagIds.push(tagId);
        }
    },
    isTagSelected(tagId, type) {
        return this.formData[type].tagIds.includes(tagId);
    }
}"
     @open-create-modal.window="openModal()"
     @close-create-modal.window="closeModal()"
     @item-created.window="resetFormData(); closeModal();">

    <!-- Form Container -->
    <div
        x-show="isOpen"
        x-transition:enter="transition-transform ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition-transform ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        @click.away="closeModal()"
        class="fixed bottom-0 left-1/2 -translate-x-1/2 w-3/4 z-50 px-4 bg-white dark:bg-zinc-900 rounded-t-3xl shadow-2xl border-2 border-zinc-300 dark:border-zinc-600"
        x-cloak
    >
        <div class="py-4 space-y-4" @click.stop>
            <!-- Top Section: Title Input Field -->
            <div class="space-y-3">
                <!-- Tabs -->
                <div class="flex gap-2">
                    <button
                        @click.stop="switchTab('task')"
                        :class="activeTab === 'task' ? 'bg-blue-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                    >
                        Task
                    </button>
                    <button
                        @click.stop="switchTab('event')"
                        :class="activeTab === 'event' ? 'bg-blue-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                    >
                        Event
                    </button>
                    <button
                        @click.stop="switchTab('project')"
                        :class="activeTab === 'project' ? 'bg-blue-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                    >
                        Project
                    </button>
                </div>

                <!-- Input Field and Submit Button -->
                <div class="flex gap-2 items-center">
                    <input
                        type="text"
                        x-model="inputValue"
                        @keydown.enter.prevent="activeTab === 'task' ? submitTask() : (activeTab === 'event' ? submitEvent() : submitProject())"
                        @click.stop
                        :placeholder="activeTab === 'task' ? 'Enter task title...' : (activeTab === 'event' ? 'Enter event title...' : 'Enter project name...')"
                        class="flex-1 px-6 py-4 rounded-full border-2 border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    />
                    <button
                        @click.stop="activeTab === 'task' ? submitTask() : (activeTab === 'event' ? submitEvent() : submitProject())"
                        wire:loading.attr="disabled"
                        class="px-6 py-4 rounded-full bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700 text-white font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 whitespace-nowrap"
                    >
                        <span wire:loading.remove>
                            <span x-text="activeTab === 'task' ? 'Create Task' : (activeTab === 'event' ? 'Create Event' : 'Create Project')"></span>
                        </span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Creating...</span>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Bottom Section: Property Buttons -->
            <div class="flex flex-wrap gap-2" @click.stop>
                <template x-if="activeTab === 'task'">
                    <div class="contents">
                    <!-- Task Priority -->
                    <x-inline-create-dropdown dropdown-class="w-48">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span class="text-sm font-medium">Priority</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.task.priority || 'Medium'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.task.priority = 'low')"
                                :class="formData.task.priority === 'low' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Low
                            </button>
                            <button
                                @click="select(() => formData.task.priority = 'medium')"
                                :class="formData.task.priority === 'medium' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Medium
                            </button>
                            <button
                                @click="select(() => formData.task.priority = 'high')"
                                :class="formData.task.priority === 'high' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                High
                            </button>
                            <button
                                @click="select(() => formData.task.priority = 'urgent')"
                                :class="formData.task.priority === 'urgent' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Urgent
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Complexity -->
                    <x-inline-create-dropdown dropdown-class="w-48">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span class="text-sm font-medium">Complexity</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.task.complexity || 'Moderate'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.task.complexity = 'simple')"
                                :class="formData.task.complexity === 'simple' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Simple
                            </button>
                            <button
                                @click="select(() => formData.task.complexity = 'moderate')"
                                :class="formData.task.complexity === 'moderate' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Moderate
                            </button>
                            <button
                                @click="select(() => formData.task.complexity = 'complex')"
                                :class="formData.task.complexity === 'complex' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Complex
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Duration -->
                    <x-inline-create-dropdown dropdown-class="w-48 max-h-60 overflow-y-auto">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium">Duration</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.duration ? formData.task.duration + ' min' : 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            @foreach([15, 30, 45, 60, 90, 120, 180, 240, 300] as $minutes)
                                <button
                                    @click="select(() => formData.task.duration = {{ $minutes }})"
                                    :class="formData.task.duration === {{ $minutes }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                >
                                    {{ $minutes }} minutes
                                </button>
                            @endforeach
                            <button
                                @click="select(() => formData.task.duration = null)"
                                :class="formData.task.duration === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Clear
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Start Date & Time -->
                    <x-inline-create-dropdown dropdown-class="w-64 p-4">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">Start Date & Time</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.startDatetime || 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <div class="space-y-3 px-3 pb-3 pt-1">
                                <flux:input x-model="formData.task.startDatetime" type="datetime-local" />
                                <button
                                    @click="select(() => formData.task.startDatetime = null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear
                                </button>
                            </div>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task End Date & Time -->
                    <x-inline-create-dropdown dropdown-class="w-64 p-4">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">End Date & Time</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.endDatetime || 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <div class="space-y-3 px-3 pb-3 pt-1">
                                <flux:input x-model="formData.task.endDatetime" type="datetime-local" />
                                <button
                                    @click="select(() => formData.task.endDatetime = null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear
                                </button>
                            </div>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Project -->
                    <x-inline-create-dropdown dropdown-class="w-48 max-h-60 overflow-y-auto">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <span class="text-sm font-medium">Project</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.projectId ? 'Selected' : 'None'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.task.projectId = null)"
                                :class="formData.task.projectId === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                None
                            </button>
                            @foreach($this->availableProjects as $project)
                            <button
                                wire:key="project-{{ $project->id }}"
                                @click="select(() => formData.task.projectId = {{ $project->id }})"
                                :class="formData.task.projectId === {{ $project->id }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                {{ $project->name }}
                            </button>
                            @endforeach
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Tags -->
                    <x-inline-create-dropdown dropdown-class="w-64 max-h-60 overflow-y-auto">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span class="text-sm font-medium">Tags</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.tagIds.length > 0 ? formData.task.tagIds.length + ' selected' : 'None'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            @foreach($this->availableTags as $tag)
                                <label wire:key="task-tag-{{ $tag->id }}" class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="isTagSelected({{ $tag->id }}, 'task')"
                                        @change="select(() => toggleTag({{ $tag->id }}, 'task'), false)"
                                        class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="ml-2 text-sm">{{ $tag->name }}</span>
                                </label>
                            @endforeach
                            @if($this->availableTags->isEmpty())
                                <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
                            @endif
                        </x-slot:options>
                    </x-inline-create-dropdown>
                    </div>
                </template>
                <template x-if="activeTab === 'event'">
                    <div class="contents">
                    <!-- Event Status -->
                    <x-inline-create-dropdown dropdown-class="w-48">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                            <span class="text-sm font-medium">Status</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.event.status || 'Scheduled'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.event.status = 'scheduled')"
                                :class="formData.event.status === 'scheduled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Scheduled
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'ongoing')"
                                :class="formData.event.status === 'ongoing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                In Progress
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'tentative')"
                                :class="formData.event.status === 'tentative' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Tentative
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'completed')"
                                :class="formData.event.status === 'completed' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Completed
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'cancelled')"
                                :class="formData.event.status === 'cancelled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Cancelled
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Event Start Date & Time -->
                    <x-inline-create-dropdown dropdown-class="w-64 p-4">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">Start Date & Time</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.event.startDatetime || 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <div class="space-y-3 px-3 pb-3 pt-1">
                                <flux:input x-model="formData.event.startDatetime" type="datetime-local" />
                                <button
                                    @click="select(() => formData.event.startDatetime = null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear
                                </button>
                            </div>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Event End Date & Time -->
                    <x-inline-create-dropdown dropdown-class="w-64 p-4">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">End Date & Time</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.event.endDatetime || 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <div class="space-y-3 px-3 pb-3 pt-1">
                                <flux:input x-model="formData.event.endDatetime" type="datetime-local" />
                                <button
                                    @click="select(() => formData.event.endDatetime = null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear
                                </button>
                            </div>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Event Tags -->
                    <x-inline-create-dropdown dropdown-class="w-64 max-h-60 overflow-y-auto">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span class="text-sm font-medium">Tags</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.event.tagIds.length > 0 ? formData.event.tagIds.length + ' selected' : 'None'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            @foreach($this->availableTags as $tag)
                                <label wire:key="event-tag-{{ $tag->id }}" class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="isTagSelected({{ $tag->id }}, 'event')"
                                        @change="select(() => toggleTag({{ $tag->id }}, 'event'), false)"
                                        class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="ml-2 text-sm">{{ $tag->name }}</span>
                                </label>
                            @endforeach
                            @if($this->availableTags->isEmpty())
                                <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
                            @endif
                        </x-slot:options>
                    </x-inline-create-dropdown>
                    </div>
                </template>
                <template x-if="activeTab === 'project'">
                    <div class="contents">
                        <!-- Project Start Date -->
                    <x-inline-create-dropdown dropdown-class="w-64 p-4">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">Start Date</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.project.startDate || 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <div class="space-y-3 px-3 pb-3 pt-1">
                                <flux:input x-model="formData.project.startDate" type="date" />
                                <button
                                    @click="select(() => formData.project.startDate = null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear
                                </button>
                            </div>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                        <!-- Project End Date -->
                    <x-inline-create-dropdown dropdown-class="w-64 p-4">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium">End Date</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.project.endDate || 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <div class="space-y-3 px-3 pb-3 pt-1">
                                <flux:input x-model="formData.project.endDate" type="date" />
                                <button
                                    @click="select(() => formData.project.endDate = null)"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear
                                </button>
                            </div>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                        <!-- Project Tags -->
                    <x-inline-create-dropdown dropdown-class="w-64 max-h-60 overflow-y-auto">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span class="text-sm font-medium">Tags</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.project.tagIds.length > 0 ? formData.project.tagIds.length + ' selected' : 'None'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            @foreach($this->availableTags as $tag)
                                <label wire:key="project-tag-{{ $tag->id }}" class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="isTagSelected({{ $tag->id }}, 'project')"
                                        @change="select(() => toggleTag({{ $tag->id }}, 'project'), false)"
                                        class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="ml-2 text-sm">{{ $tag->name }}</span>
                                </label>
                            @endforeach
                            @if($this->availableTags->isEmpty())
                                <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
                            @endif
                        </x-slot:options>
                    </x-inline-create-dropdown>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

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
        $this->taskStartDate = Carbon::today()->toDateString();
        $this->taskStartTime = null;
        $this->taskEndDate = null;
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

            DB::transaction(function () use ($validated) {
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
                'eventStatus' => ['nullable', 'string', 'in:scheduled,cancelled,completed,tentative'],
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
    openDropdown: null,
    formData: {
        task: {
            title: '',
            status: 'to_do',
            priority: 'medium',
            complexity: 'moderate',
            duration: 60,
            startDate: '{{ Carbon::today()->toDateString() }}',
            startTime: null,
            endDate: null,
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
        this.openDropdown = null;
        $flux.modal('create-item').show();
    },
    closeModal() {
        $flux.modal('create-item').close();
        this.openDropdown = null;
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
    },
    resetFormData() {
        this.formData = {
            task: {
                title: '',
                status: 'to_do',
                priority: 'medium',
                complexity: 'moderate',
                duration: 60,
                startDate: '{{ Carbon::today()->toDateString() }}',
                startTime: null,
                endDate: null,
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
        $wire.taskStartDate = this.formData.task.startDate;
        $wire.taskStartTime = this.formData.task.startTime;
        $wire.taskEndDate = this.formData.task.endDate;
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
                    <flux:input x-model="formData.task.title" @keydown.enter.prevent="submitTask()" label="Title" placeholder="Enter task title" required />
                </div>
                <div x-show="activeTab === 'event'">
                    <flux:input x-model="formData.event.title" @keydown.enter.prevent="submitEvent()" label="Title" placeholder="Enter event title" required />
                </div>
                <div x-show="activeTab === 'project'">
                    <flux:input x-model="formData.project.name" @keydown.enter.prevent="submitProject()" label="Name" placeholder="Enter project name" required />
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.status === 'to_do' ? 'To Do' : (formData.task.status === 'doing' ? 'In Progress' : 'Done')"></span>
                        </button>
                        <div
                            x-show="isOpen('task-status')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                @click="formData.task.status = 'to_do'; openDropdown = null"
                                :class="formData.task.status === 'to_do' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                To Do
                            </button>
                            <button
                                @click="formData.task.status = 'doing'; openDropdown = null"
                                :class="formData.task.status === 'doing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                In Progress
                            </button>
                            <button
                                @click="formData.task.status = 'done'; openDropdown = null"
                                :class="formData.task.status === 'done' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.task.priority || 'Medium'"></span>
                        </button>
                        <div
                            x-show="isOpen('task-priority')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                @click="formData.task.priority = 'low'; openDropdown = null"
                                :class="formData.task.priority === 'low' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Low
                            </button>
                            <button
                                @click="formData.task.priority = 'medium'; openDropdown = null"
                                :class="formData.task.priority === 'medium' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Medium
                            </button>
                            <button
                                @click="formData.task.priority = 'high'; openDropdown = null"
                                :class="formData.task.priority === 'high' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                High
                            </button>
                            <button
                                @click="formData.task.priority = 'urgent'; openDropdown = null"
                                :class="formData.task.priority === 'urgent' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.task.complexity || 'Moderate'"></span>
                        </button>
                        <div
                            x-show="isOpen('task-complexity')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                @click="formData.task.complexity = 'simple'; openDropdown = null"
                                :class="formData.task.complexity === 'simple' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Simple
                            </button>
                            <button
                                @click="formData.task.complexity = 'moderate'; openDropdown = null"
                                :class="formData.task.complexity === 'moderate' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Moderate
                            </button>
                            <button
                                @click="formData.task.complexity = 'complex'; openDropdown = null"
                                :class="formData.task.complexity === 'complex' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.duration ? formData.task.duration + ' min' : 'Not set'"></span>
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
                                    @click="formData.task.duration = {{ $minutes }}; openDropdown = null"
                                    :class="formData.task.duration === {{ $minutes }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                >
                                    {{ $minutes }} minutes
                                </button>
                            @endforeach
                            <button
                                @click="formData.task.duration = null; openDropdown = null"
                                :class="formData.task.duration === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="(formData.task.startDate || formData.task.endDate) ? 'Set' : 'Not set'"></span>
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
                                    <flux:input x-model="formData.task.startDate" type="date" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Start Time</label>
                                    <flux:input x-model="formData.task.startTime" type="time" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">End Date</label>
                                    <flux:input x-model="formData.task.endDate" type="date" />
                                </div>
                                <button
                                    @click="formData.task.startDate = null; formData.task.startTime = null; formData.task.endDate = null"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.projectId ? 'Selected' : 'None'"></span>
                        </button>
                        <template x-if="isOpen('task-project')">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="openDropdown = null"
                            >
                                <button
                                    @click="formData.task.projectId = null; openDropdown = null"
                                    :class="formData.task.projectId === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                >
                                    None
                                </button>
                                @foreach($this->availableProjects as $project)
                                <button
                                    wire:key="project-{{ $project->id }}"
                                    @click="formData.task.projectId = {{ $project->id }}; openDropdown = null"
                                    :class="formData.task.projectId === {{ $project->id }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.tagIds.length > 0 ? formData.task.tagIds.length + ' selected' : 'None'"></span>
                        </button>
                        <template x-if="isOpen('task-tags')">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="openDropdown = null"
                            >
                                @foreach($this->availableTags as $tag)
                                    <label wire:key="task-tag-{{ $tag->id }}" class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="isTagSelected({{ $tag->id }}, 'task')"
                                            @change="toggleTag({{ $tag->id }}, 'task')"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.event.status || 'Scheduled'"></span>
                        </button>
                        <div
                            x-show="isOpen('event-status')"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                @click="formData.event.status = 'scheduled'; openDropdown = null"
                                :class="formData.event.status === 'scheduled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Scheduled
                            </button>
                            <button
                                @click="formData.event.status = 'tentative'; openDropdown = null"
                                :class="formData.event.status === 'tentative' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Tentative
                            </button>
                            <button
                                @click="formData.event.status = 'completed'; openDropdown = null"
                                :class="formData.event.status === 'completed' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Completed
                            </button>
                            <button
                                @click="formData.event.status = 'cancelled'; openDropdown = null"
                                :class="formData.event.status === 'cancelled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="(formData.event.startDatetime || formData.event.endDatetime) ? 'Set' : 'Not set'"></span>
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
                                    <flux:input x-model="formData.event.startDatetime" type="datetime-local" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">End Date & Time</label>
                                    <flux:input x-model="formData.event.endDatetime" type="datetime-local" />
                                </div>
                                <button
                                    @click="formData.event.startDatetime = null; formData.event.endDatetime = null"
                                    class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
                                >
                                    Clear all
                                </button>
                            </div>
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.event.tagIds.length > 0 ? formData.event.tagIds.length + ' selected' : 'None'"></span>
                        </button>
                        <template x-if="isOpen('event-tags')">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="openDropdown = null"
                            >
                                @foreach($this->availableTags as $tag)
                                    <label wire:key="event-tag-{{ $tag->id }}" class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="isTagSelected({{ $tag->id }}, 'event')"
                                            @change="toggleTag({{ $tag->id }}, 'event')"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="(formData.project.startDate || formData.project.endDate) ? 'Set' : 'Not set'"></span>
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
                                    <flux:input x-model="formData.project.startDate" type="date" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">End Date</label>
                                    <flux:input x-model="formData.project.endDate" type="date" />
                                </div>
                                <button
                                    @click="formData.project.startDate = null; formData.project.endDate = null"
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
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.project.tagIds.length > 0 ? formData.project.tagIds.length + ' selected' : 'None'"></span>
                        </button>
                        <template x-if="isOpen('project-tags')">
                            <div
                                x-cloak
                                x-transition
                                class="absolute z-50 mt-1 w-64 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                                @click.away="openDropdown = null"
                            >
                                @foreach($this->availableTags as $tag)
                                    <label wire:key="project-tag-{{ $tag->id }}" class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="isTagSelected({{ $tag->id }}, 'project')"
                                            @change="toggleTag({{ $tag->id }}, 'project')"
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
                <flux:button variant="ghost" @click="$flux.modal('create-item').close(); resetFormData(); $wire.call('resetForm');">
                    Cancel
                </flux:button>
                <flux:button
                    x-show="activeTab === 'task'"
                    variant="primary"
                    @click="submitTask()"
                    wire:loading.attr="disabled"
                    wire:target="createTask"
                >
                    <span wire:loading.remove wire:target="createTask">Create Task</span>
                    <span wire:loading wire:target="createTask" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </flux:button>
                <flux:button
                    x-show="activeTab === 'event'"
                    variant="primary"
                    @click="submitEvent()"
                    wire:loading.attr="disabled"
                    wire:target="createEvent"
                >
                    <span wire:loading.remove wire:target="createEvent">Create Event</span>
                    <span wire:loading wire:target="createEvent" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </flux:button>
                <flux:button
                    x-show="activeTab === 'project'"
                    variant="primary"
                    @click="submitProject()"
                    wire:loading.attr="disabled"
                    wire:target="createProject"
                >
                    <span wire:loading.remove wire:target="createProject">Create Project</span>
                    <span wire:loading wire:target="createProject" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

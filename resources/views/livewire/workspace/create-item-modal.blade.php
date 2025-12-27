<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\ProjectStoreRequest;
use App\Models\Task;
use App\Models\Event;
use App\Models\Project;
use App\Enums\TaskStatus;
use App\Enums\EventStatus;
use Illuminate\Support\Collection;

new class extends Component {
    public bool $isOpen = false;
    public string $activeTab = 'task';

    // Task form fields
    public string $taskTitle = '';
    public string $taskDescription = '';
    public string $taskStatus = 'to_do';
    public string $taskPriority = 'medium';
    public string $taskComplexity = 'moderate';
    public string $taskDuration = '60';
    public string $taskStartDate;
    public string $taskStartTime = '';
    public string $taskEndDate;
    public string $taskProjectId = '';

    public function mount(): void
    {
        $this->taskStartDate = now()->format('Y-m-d');
        $this->taskEndDate = now()->addWeek()->format('Y-m-d');
    }

    // Event form fields
    public string $eventTitle = '';
    public string $eventDescription = '';
    public string $eventStartDatetime = '';
    public string $eventEndDatetime = '';
    public bool $eventAllDay = false;
    public string $eventLocation = '';
    public string $eventColor = '#3b82f6';
    public string $eventStatus = 'scheduled';

    // Project form fields
    public string $projectName = '';
    public string $projectDescription = '';
    public string $projectStartDate = '';
    public string $projectEndDate = '';

    #[On('open-create-modal')]
    public function openModal(string $type = 'task'): void
    {
        $this->activeTab = $type;
        $this->isOpen = true;
        $this->resetForm();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        // Reset task fields
        $this->taskTitle = '';
        $this->taskDescription = '';
        $this->taskStatus = 'to_do';
        $this->taskPriority = 'medium';
        $this->taskComplexity = 'moderate';
        $this->taskDuration = '60';
        $this->taskStartDate = now()->format('Y-m-d');
        $this->taskStartTime = '';
        $this->taskEndDate = now()->addWeek()->format('Y-m-d');
        $this->taskProjectId = '';

        // Reset event fields
        $this->eventTitle = '';
        $this->eventDescription = '';
        $this->eventStartDatetime = '';
        $this->eventEndDatetime = '';
        $this->eventAllDay = false;
        $this->eventLocation = '';
        $this->eventColor = '#3b82f6';
        $this->eventStatus = 'scheduled';

        // Reset project fields
        $this->projectName = '';
        $this->projectDescription = '';
        $this->projectStartDate = '';
        $this->projectEndDate = '';
    }

    public function createTask(): void
    {
        $validated = $this->validate([
            'taskTitle' => 'required|string|max:255',
            'taskDescription' => 'nullable|string',
            'taskStatus' => 'required|string|in:to_do,doing,done',
            'taskPriority' => 'required|string|in:low,medium,high,urgent',
            'taskComplexity' => 'required|string|in:simple,moderate,complex',
            'taskDuration' => 'nullable|integer|min:1',
            'taskStartDate' => 'required|date',
            'taskStartTime' => 'nullable|date_format:H:i',
            'taskEndDate' => 'required|date|after_or_equal:taskStartDate',
            'taskProjectId' => 'nullable|exists:projects,id',
        ], [], [
            'taskTitle' => 'title',
            'taskDescription' => 'description',
            'taskStatus' => 'status',
            'taskPriority' => 'priority',
            'taskComplexity' => 'complexity',
            'taskDuration' => 'duration',
            'taskStartDate' => 'start date',
            'taskStartTime' => 'start time',
            'taskEndDate' => 'end date',
            'taskProjectId' => 'project',
        ]);

        // Convert H:i format to H:i:s for database storage
        $startTime = $this->taskStartTime ? $this->taskStartTime . ':00' : null;

        Task::create([
            'user_id' => auth()->id(),
            'title' => $this->taskTitle,
            'description' => $this->taskDescription ?: null,
            'status' => $this->taskStatus,
            'priority' => $this->taskPriority,
            'complexity' => $this->taskComplexity,
            'duration' => $this->taskDuration ?: null,
            'start_date' => $this->taskStartDate,
            'start_time' => $startTime,
            'end_date' => $this->taskEndDate,
            'project_id' => $this->taskProjectId ?: null,
        ]);

        $this->dispatch('task-created');
        $this->closeModal();

        session()->flash('message', 'Task created successfully!');
    }

    public function createEvent(): void
    {
        $validated = $this->validate([
            'eventTitle' => 'required|string|max:255',
            'eventDescription' => 'nullable|string',
            'eventStartDatetime' => 'required|date',
            'eventEndDatetime' => 'required|date|after:eventStartDatetime',
            'eventAllDay' => 'boolean',
            'eventLocation' => 'nullable|string|max:255',
            'eventColor' => 'nullable|string|max:7',
            'eventStatus' => 'nullable|string|in:scheduled,cancelled,completed,tentative',
        ], [], [
            'eventTitle' => 'title',
            'eventDescription' => 'description',
            'eventStartDatetime' => 'start date/time',
            'eventEndDatetime' => 'end date/time',
            'eventAllDay' => 'all day',
            'eventLocation' => 'location',
            'eventColor' => 'color',
            'eventStatus' => 'status',
        ]);

        Event::create([
            'user_id' => auth()->id(),
            'title' => $this->eventTitle,
            'description' => $this->eventDescription ?: null,
            'start_datetime' => $this->eventStartDatetime,
            'end_datetime' => $this->eventEndDatetime,
            'all_day' => $this->eventAllDay,
            'timezone' => config('app.timezone'),
            'location' => $this->eventLocation ?: null,
            'color' => $this->eventColor,
            'status' => $this->eventStatus ?: 'scheduled',
        ]);

        $this->dispatch('event-created');
        $this->closeModal();

        session()->flash('message', 'Event created successfully!');
    }

    public function createProject(): void
    {
        $validated = $this->validate([
            'projectName' => 'required|string|max:255',
            'projectDescription' => 'nullable|string',
            'projectStartDate' => 'nullable|date',
            'projectEndDate' => 'nullable|date|after_or_equal:projectStartDate',
        ], [], [
            'projectName' => 'name',
            'projectDescription' => 'description',
            'projectStartDate' => 'start date',
            'projectEndDate' => 'end date',
        ]);

        Project::create([
            'user_id' => auth()->id(),
            'name' => $this->projectName,
            'description' => $this->projectDescription ?: null,
            'start_date' => $this->projectStartDate ?: null,
            'end_date' => $this->projectEndDate ?: null,
        ]);

        $this->dispatch('project-created');
        $this->closeModal();

        session()->flash('message', 'Project created successfully!');
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetValidation();
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();
    }
}; ?>

<div>
    <flux:modal wire:model="isOpen" class="min-w-[600px]">
        <div>
            <flux:heading size="lg">Create New Item</flux:heading>

            <!-- Tab Buttons -->
            <div class="flex gap-2 my-4 border-b border-zinc-200 dark:border-zinc-700">
                <button
                    wire:click="switchTab('task')"
                    class="px-4 py-2 font-medium text-sm transition {{ $activeTab === 'task' ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    Task
                </button>
                <button
                    wire:click="switchTab('event')"
                    class="px-4 py-2 font-medium text-sm transition {{ $activeTab === 'event' ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    Event
                </button>
                <button
                    wire:click="switchTab('project')"
                    class="px-4 py-2 font-medium text-sm transition {{ $activeTab === 'project' ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    Project
                </button>
            </div>

            <!-- Task Form -->
            @if($activeTab === 'task')
                <form wire:submit="createTask" class="space-y-4">
                    <flux:input wire:model="taskTitle" label="Title" required />

                    <flux:textarea wire:model="taskDescription" label="Description" rows="3" />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:select wire:model="taskStatus" label="Status" required>
                            <option value="to_do">To Do</option>
                            <option value="doing">In Progress</option>
                            <option value="done">Done</option>
                        </flux:select>

                        <flux:select wire:model="taskPriority" label="Priority">
                            <option value="">None</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </flux:select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:select wire:model="taskComplexity" label="Complexity">
                            <option value="">None</option>
                            <option value="simple">Simple</option>
                            <option value="moderate">Moderate</option>
                            <option value="complex">Complex</option>
                        </flux:select>

                        <flux:input wire:model="taskDuration" label="Duration (minutes)" type="number" min="1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="taskStartDate" label="Start Date" type="date" required />
                        <flux:input wire:model="taskEndDate" label="Due Date" type="date" required />
                    </div>

                    <flux:input wire:model="taskStartTime" label="Start Time (optional)" type="time" />

                    <flux:select wire:model="taskProjectId" label="Project">
                        <option value="">No Project</option>
                        @foreach($this->projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </flux:select>

                    <div class="flex justify-end gap-2 mt-6">
                        <flux:button variant="ghost" wire:click="closeModal">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Create Task</flux:button>
                    </div>
                </form>
            @endif

            <!-- Event Form -->
            @if($activeTab === 'event')
                <form wire:submit="createEvent" class="space-y-4">
                    <flux:input wire:model="eventTitle" label="Title" required />

                    <flux:textarea wire:model="eventDescription" label="Description" rows="3" />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="eventStartDatetime" label="Start Date & Time" type="datetime-local" required />
                        <flux:input wire:model="eventEndDatetime" label="End Date & Time" type="datetime-local" required />
                    </div>

                    <flux:checkbox wire:model="eventAllDay" label="All Day Event" />

                    <flux:input wire:model="eventLocation" label="Location" />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="eventColor" label="Color" type="color" />

                        <flux:select wire:model="eventStatus" label="Status">
                            <option value="scheduled">Scheduled</option>
                            <option value="tentative">Tentative</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </flux:select>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <flux:button variant="ghost" wire:click="closeModal">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Create Event</flux:button>
                    </div>
                </form>
            @endif

            <!-- Project Form -->
            @if($activeTab === 'project')
                <form wire:submit="createProject" class="space-y-4">
                    <flux:input wire:model="projectName" label="Name" required />

                    <flux:textarea wire:model="projectDescription" label="Description" rows="3" />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="projectStartDate" label="Start Date" type="date" />
                        <flux:input wire:model="projectEndDate" label="End Date" type="date" />
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <flux:button variant="ghost" wire:click="closeModal">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Create Project</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</div>

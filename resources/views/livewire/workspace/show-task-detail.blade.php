<?php

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $isOpen = false;

    public ?Task $task = null;

    public bool $editMode = false;

    // Edit fields
    public string $title = '';

    public string $description = '';

    public string $status = '';

    public string $priority = '';

    public string $complexity = '';

    public string $duration = '';

    public string $startDate = '';

    public string $startTime = '';

    public string $endDate = '';

    public string $projectId = '';

    public string $eventId = '';

    #[On('view-task-detail')]
    public function openModal(int $id): void
    {
        $this->task = Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions'])
            ->findOrFail($id);

        $this->authorize('view', $this->task);

        $this->isOpen = true;
        $this->editMode = false;
        $this->loadTaskData();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->task = null;
        $this->editMode = false;
        $this->resetValidation();
    }

    public function toggleEditMode(): void
    {
        $this->editMode = ! $this->editMode;
        if ($this->editMode) {
            $this->loadTaskData();
        }
        $this->resetValidation();
    }

    protected function loadTaskData(): void
    {
        if (! $this->task) {
            return;
        }

        $this->title = $this->task->title;
        $this->description = $this->task->description ?? '';
        $this->status = $this->task->status?->value ?? 'to_do';
        $this->priority = $this->task->priority?->value ?? '';
        $this->complexity = $this->task->complexity?->value ?? '';
        $this->duration = $this->task->duration ? (string) $this->task->duration : '';
        $this->startDate = $this->task->start_date?->format('Y-m-d') ?? '';
        $this->startTime = $this->task->start_time ? substr($this->task->start_time, 0, 5) : '';
        $this->endDate = $this->task->end_date?->format('Y-m-d') ?? '';
        $this->projectId = (string) ($this->task->project_id ?? '');
        $this->eventId = (string) ($this->task->event_id ?? '');
    }

    public function save(): void
    {
        $this->authorize('update', $this->task);

        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:to_do,doing,done',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'complexity' => 'nullable|string|in:simple,moderate,complex',
            'duration' => 'nullable|integer|min:1',
            'startDate' => 'nullable|date',
            'startTime' => 'nullable|date_format:H:i',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'projectId' => 'nullable|exists:projects,id',
        ]);

        // Convert H:i format to H:i:s for database storage
        $startTime = $this->startTime ? $this->startTime.':00' : null;

        $this->task->update([
            'title' => $this->title,
            'description' => $this->description ?: null,
            'status' => $this->status ?: null,
            'priority' => $this->priority ?: null,
            'complexity' => $this->complexity ?: null,
            'duration' => $this->duration ?: null,
            'start_date' => $this->startDate ?: null,
            'start_time' => $startTime,
            'end_date' => $this->endDate ?: null,
            'project_id' => $this->projectId ?: null,
        ]);

        $this->task->refresh();
        $this->editMode = false;
        $this->dispatch('task-updated');
        session()->flash('message', 'Task updated successfully!');
    }

    public function deleteTask(): void
    {
        $this->authorize('delete', $this->task);

        $this->task->delete();
        $this->closeModal();
        $this->dispatch('task-deleted');
        session()->flash('message', 'Task deleted successfully!');
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::accessibleBy(auth()->user())
            ->orderBy('name')
            ->get();
    }
}; ?>

<div>
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout">
        @if($task)
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        @if($editMode)
                            <flux:input wire:model="title" label="Title" required />
                        @else
                            <flux:heading size="lg">{{ $task->title }}</flux:heading>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 ml-4">
                        @if($editMode)
                            <flux:button variant="primary" wire:click="save">
                                Save
                            </flux:button>
                            <flux:button variant="ghost" wire:click="toggleEditMode">
                                Cancel
                            </flux:button>
                        @else
                            <flux:button variant="ghost" wire:click="toggleEditMode">
                                Edit
                            </flux:button>
                            <flux:button
                                variant="danger"
                                wire:click="deleteTask"
                                wire:confirm="Are you sure you want to delete this task?"
                            >
                                Delete
                            </flux:button>
                        @endif
                    </div>
                </div>

                <!-- Description -->
                <div>
                    @if($editMode)
                        <flux:textarea wire:model="description" label="Description" rows="4" />
                    @else
                        <flux:heading size="sm" class="mb-2">Description</flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $task->description ?: 'No description provided.' }}
                        </p>
                    @endif
                </div>

                <!-- Task Details Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Status -->
                    <div>
                        @if($editMode)
                            <flux:select wire:model="status" label="Status">
                                <option value="">Select Status</option>
                                <option value="to_do">To Do</option>
                                <option value="doing">In Progress</option>
                                <option value="done">Done</option>
                            </flux:select>
                        @else
                            <flux:heading size="sm">Status</flux:heading>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded mt-2 {{ match($task->status->value) {
                                'to_do' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300',
                                'doing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                'done' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                            } }}">
                                {{ match($task->status->value) {
                                    'to_do' => 'To Do',
                                    'doing' => 'In Progress',
                                    'done' => 'Done',
                                } }}
                            </span>
                        @endif
                    </div>

                    <!-- Priority -->
                    <div>
                        @if($editMode)
                            <flux:select wire:model="priority" label="Priority">
                                <option value="">None</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </flux:select>
                        @else
                            <flux:heading size="sm">Priority</flux:heading>
                            @if($task->priority)
                                <span class="inline-flex items-center gap-2 mt-2">
                                    <span class="w-3 h-3 rounded-full {{ match($task->priority->value) {
                                        'low' => 'bg-zinc-400',
                                        'medium' => 'bg-yellow-400',
                                        'high' => 'bg-orange-500',
                                        'urgent' => 'bg-red-500',
                                    } }}"></span>
                                    <span class="text-sm">{{ ucfirst($task->priority->value) }}</span>
                                </span>
                            @else
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">Not set</p>
                            @endif
                        @endif
                    </div>

                    <!-- Complexity -->
                    <div>
                        @if($editMode)
                            <flux:select wire:model="complexity" label="Complexity">
                                <option value="">None</option>
                                <option value="simple">Simple</option>
                                <option value="moderate">Moderate</option>
                                <option value="complex">Complex</option>
                            </flux:select>
                        @else
                            <flux:heading size="sm">Complexity</flux:heading>
                            @if($task->complexity)
                                <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded mt-2 {{ match($task->complexity->value) {
                                    'simple' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                    'moderate' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                    'complex' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                } }}">
                                    {{ ucfirst($task->complexity->value) }}
                                </span>
                            @else
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">Not set</p>
                            @endif
                        @endif
                    </div>

                    <!-- Duration -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="duration" label="Duration (minutes)" type="number" min="1" />
                        @else
                            <flux:heading size="sm">Duration</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">{{ $task->duration ? $task->duration . ' minutes' : 'Not set' }}</p>
                        @endif
                    </div>

                    <!-- Start Date -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="startDate" label="Start Date (optional)" type="date" />
                        @else
                            <flux:heading size="sm">Start Date</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                {{ $task->start_date?->format('M j, Y') ?? 'Not set' }}
                                @if($task->start_time)
                                    <span class="text-zinc-500 dark:text-zinc-400">at {{ substr($task->start_time, 0, 5) }}</span>
                                @endif
                            </p>
                        @endif
                    </div>

                    <!-- End Date -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="endDate" label="Due Date (optional)" type="date" />
                        @else
                            <flux:heading size="sm">Due Date</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2 {{ $task->end_date?->isPast() && $task->status->value !== 'done' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                {{ $task->end_date?->format('M j, Y') ?? 'Not set' }}
                            </p>
                        @endif
                    </div>

                    <!-- Start Time -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="startTime" label="Start Time (optional)" type="time" />
                        @else
                            @if($task->start_time)
                                <flux:heading size="sm">Start Time</flux:heading>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">{{ substr($task->start_time, 0, 5) }}</p>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- Project -->
                <div>
                    @if($editMode)
                        <flux:select wire:model="projectId" label="Project">
                            <option value="">No Project</option>
                            @foreach($this->projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:heading size="sm">Project</flux:heading>
                        @if($task->project)
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $task->project->name }}
                                </a>
                            </p>
                        @else
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">Not assigned to a project</p>
                        @endif
                    @endif
                </div>

                <!-- Tags -->
                @if(!$editMode && $task->tags->isNotEmpty())
                    <div>
                        <flux:heading size="sm">Tags</flux:heading>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($task->tags as $tag)
                                <span class="inline-flex items-center px-3 py-1 text-sm rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ $tag->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Reminders -->
                @if(!$editMode && $task->reminders->isNotEmpty())
                    <div>
                        <flux:heading size="sm">Reminders</flux:heading>
                        <div class="mt-2 space-y-2">
                            @foreach($task->reminders as $reminder)
                                <div class="text-sm text-zinc-600 dark:text-zinc-400 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <span>{{ $reminder->trigger_time->format('M j, Y g:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Pomodoro Sessions -->
                @if(!$editMode && $task->pomodoroSessions->isNotEmpty())
                    <div>
                        <flux:heading size="sm">Pomodoro Sessions</flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                            {{ $task->pomodoroSessions->count() }} session(s) completed
                        </p>
                    </div>
                @endif

                <!-- Collaboration Placeholder -->
                @if(!$editMode)
                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                        <flux:heading size="sm">Collaboration</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                            Collaboration features coming soon...
                        </p>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>

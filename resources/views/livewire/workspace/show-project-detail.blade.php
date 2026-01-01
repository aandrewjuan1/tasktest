<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Models\Project;
use App\Models\Tag;
use App\Enums\TaskStatus;

new class extends Component {
    public bool $isOpen = false;
    public ?Project $project = null;
    public bool $editMode = false;

    // Edit fields
    public string $name = '';
    public string $description = '';
    public string $startDate = '';
    public string $endDate = '';

    #[On('view-project-detail')]
    public function openModal(int $id): void
    {
        $this->project = Project::with(['tags', 'tasks', 'reminders'])
            ->findOrFail($id);

        $this->authorize('view', $this->project);

        $this->isOpen = true;
        $this->editMode = false;
        $this->loadProjectData();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->project = null;
        $this->editMode = false;
        $this->resetValidation();
    }

    public function toggleEditMode(): void
    {
        $this->editMode = !$this->editMode;
        if ($this->editMode) {
            $this->loadProjectData();
        }
        $this->resetValidation();
    }

    protected function loadProjectData(): void
    {
        if (!$this->project) {
            return;
        }

        $this->name = $this->project->name;
        $this->description = $this->project->description ?? '';
        $this->startDate = $this->project->start_date?->format('Y-m-d') ?? '';
        $this->endDate = $this->project->end_date?->format('Y-m-d') ?? '';
    }

    public function save(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        $this->project->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'start_date' => $this->startDate ?: null,
            'end_date' => $this->endDate ?: null,
        ]);

        $this->project->refresh();
        $this->editMode = false;
        $this->dispatch('project-updated');
        session()->flash('message', 'Project updated successfully!');
    }

    public function deleteProject(): void
    {
        $this->authorize('delete', $this->project);

        $this->project->delete();
        $this->closeModal();
        $this->dispatch('project-deleted');
        session()->flash('message', 'Project deleted successfully!');
    }

    #[Computed]
    public function progress(): array
    {
        if (!$this->project) {
            return ['total' => 0, 'completed' => 0, 'percentage' => 0];
        }

        $totalTasks = $this->project->tasks->count();
        $completedTasks = $this->project->tasks->where('status', TaskStatus::Done)->count();
        $percentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return [
            'total' => $totalTasks,
            'completed' => $completedTasks,
            'percentage' => $percentage,
        ];
    }
}; ?>

<div>
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout">
        @if($project)
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        @if($editMode)
                            <flux:input wire:model="name" label="Name" required />
                        @else
                            <flux:heading size="lg">{{ $project->name }}</flux:heading>
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
                                wire:click="deleteProject"
                                wire:confirm="Are you sure you want to delete this project?"
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
                            {{ $project->description ?: 'No description provided.' }}
                        </p>
                    @endif
                </div>

                <!-- Project Details Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Start Date -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="startDate" label="Start Date" type="date" />
                        @else
                            <flux:heading size="sm">Start Date</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                {{ $project->start_date?->format('M j, Y') ?? 'Not set' }}
                            </p>
                        @endif
                    </div>

                    <!-- End Date -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="endDate" label="End Date" type="date" />
                        @else
                            <flux:heading size="sm">End Date</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                {{ $project->end_date?->format('M j, Y') ?? 'Not set' }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Progress -->
                @if(!$editMode)
                    @php
                        $progress = $this->progress;
                    @endphp
                    <div>
                        <flux:heading size="sm">Progress</flux:heading>
                        <div class="mt-2">
                            <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-400 mb-2">
                                <span>{{ $progress['completed'] }} of {{ $progress['total'] }} tasks completed</span>
                                <span class="font-semibold">{{ $progress['percentage'] }}%</span>
                            </div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3">
                                <div class="bg-blue-600 dark:bg-blue-500 h-3 rounded-full transition-all" style="width: {{ $progress['percentage'] }}%"></div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Associated Tasks -->
                @if(!$editMode && $project->tasks->isNotEmpty())
                    <div>
                        <flux:heading size="sm">Associated Tasks</flux:heading>
                        <div class="mt-2 space-y-2">
                            @foreach($project->tasks as $task)
                                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $task->title }}
                                        </p>
                                        @if($task->end_date)
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                                Due: {{ $task->end_date->format('M j, Y') }}
                                            </p>
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded ml-3 {{ match($task->status->value) {
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
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Tags -->
                @if(!$editMode && $project->tags->isNotEmpty())
                    <div>
                        <flux:heading size="sm">Tags</flux:heading>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($project->tags as $tag)
                                <span class="inline-flex items-center px-3 py-1 text-sm rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ $tag->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Reminders -->
                @if(!$editMode && $project->reminders->isNotEmpty())
                    <div>
                        <flux:heading size="sm">Reminders</flux:heading>
                        <div class="mt-2 space-y-2">
                            @foreach($project->reminders as $reminder)
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

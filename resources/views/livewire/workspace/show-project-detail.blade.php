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
    public bool $showDeleteConfirm = false;

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
        $this->showDeleteConfirm = false;
        $this->loadProjectData();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->project = null;
        $this->showDeleteConfirm = false;
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

    public function updateField(string $field, mixed $value): void
    {
        if (!$this->project) {
            return;
        }

        $this->authorize('update', $this->project);

        $validationRules = match ($field) {
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            default => [],
        };

        if (empty($validationRules)) {
            return;
        }

        // Validate endDate after startDate if both are being set
        if ($field === 'endDate' && $this->startDate) {
            $this->validate([
                $field => array_merge($validationRules, ['after_or_equal:startDate']),
            ]);
        } else {
            $this->validate([
                $field => $validationRules,
            ]);
        }

        $updateData = [];

        switch ($field) {
            case 'name':
                $updateData['name'] = $value;
                break;
            case 'description':
                $updateData['description'] = $value ?: null;
                break;
            case 'startDate':
                $updateData['start_date'] = $value ?: null;
                break;
            case 'endDate':
                $updateData['end_date'] = $value ?: null;
                break;
        }

        $this->project->update($updateData);
        $this->project->refresh();
        $this->loadProjectData();
        $this->dispatch('project-updated');
    }

    public function confirmDelete(): void
    {
        $this->showDeleteConfirm = true;
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
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout" closeable="false">
        @if($project)
            <div class="space-y-6">
                <!-- Header -->
                <div>
                    <div class="flex-1"
                         x-data="{
                             editing: false,
                             originalValue: @js($project->name),
                             currentValue: @js($project->name),
                             debounceTimer: null,
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.name = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.name = this.originalValue;
                                 if (this.debounceTimer) {
                                     clearTimeout(this.debounceTimer);
                                     this.debounceTimer = null;
                                 }
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             },
                             handleInput() {
                                 $wire.name = this.currentValue;
                                 if (this.debounceTimer) {
                                     clearTimeout(this.debounceTimer);
                                 }
                                 this.debounceTimer = setTimeout(() => {
                                     if (this.currentValue !== this.originalValue) {
                                         $wire.updateField('name', this.currentValue).then(() => {
                                             this.originalValue = this.currentValue;
                                             this.editing = false;
                                         });
                                     }
                                 }, 500);
                             },
                             handleMouseLeave() {
                                 if (!this.editing) return;
                                 this.mouseLeaveTimer = setTimeout(() => {
                                     this.saveIfChanged();
                                 }, 300);
                             },
                             handleMouseEnter() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             },
                             saveIfChanged() {
                                 if (this.debounceTimer) {
                                     clearTimeout(this.debounceTimer);
                                     this.debounceTimer = null;
                                 }
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                                 if (this.currentValue !== this.originalValue) {
                                     $wire.updateField('name', this.currentValue).then(() => {
                                         this.originalValue = this.currentValue;
                                         this.editing = false;
                                     });
                                 } else {
                                     this.editing = false;
                                 }
                             }
                         }"
                         @mouseenter="handleMouseEnter()"
                         @mouseleave="handleMouseLeave()"
                    >
                        <div x-show="!editing" @click="startEditing()" class="cursor-pointer">
                            <flux:heading size="lg" x-text="originalValue"></flux:heading>
                        </div>
                        <div x-show="editing" x-cloak>
                            <flux:input
                                x-ref="input"
                                x-model="currentValue"
                                x-on:input="handleInput()"
                                @keydown.enter="saveIfChanged()"
                                @keydown.escape="cancelEditing()"
                                label="Name"
                                required
                            />
                            @error('name')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div
                     x-data="{
                         editing: false,
                         originalValue: @js($project->description ?? ''),
                         currentValue: @js($project->description ?? ''),
                         debounceTimer: null,
                         mouseLeaveTimer: null,
                         startEditing() {
                             this.editing = true;
                             this.currentValue = this.originalValue;
                             $wire.description = this.originalValue;
                             $nextTick(() => $refs.input?.focus());
                         },
                         cancelEditing() {
                             this.editing = false;
                             this.currentValue = this.originalValue;
                             $wire.description = this.originalValue;
                             if (this.debounceTimer) {
                                 clearTimeout(this.debounceTimer);
                                 this.debounceTimer = null;
                             }
                             if (this.mouseLeaveTimer) {
                                 clearTimeout(this.mouseLeaveTimer);
                                 this.mouseLeaveTimer = null;
                             }
                         },
                         handleInput() {
                             $wire.description = this.currentValue;
                             if (this.debounceTimer) {
                                 clearTimeout(this.debounceTimer);
                             }
                             this.debounceTimer = setTimeout(() => {
                                 if (this.currentValue !== this.originalValue) {
                                     $wire.updateField('description', this.currentValue).then(() => {
                                         this.originalValue = this.currentValue;
                                         this.editing = false;
                                     });
                                 }
                             }, 500);
                         },
                         handleMouseLeave() {
                             if (!this.editing) return;
                             this.mouseLeaveTimer = setTimeout(() => {
                                 this.saveIfChanged();
                             }, 300);
                         },
                         handleMouseEnter() {
                             if (this.mouseLeaveTimer) {
                                 clearTimeout(this.mouseLeaveTimer);
                                 this.mouseLeaveTimer = null;
                             }
                         },
                         saveIfChanged() {
                             if (this.debounceTimer) {
                                 clearTimeout(this.debounceTimer);
                                 this.debounceTimer = null;
                             }
                             if (this.mouseLeaveTimer) {
                                 clearTimeout(this.mouseLeaveTimer);
                                 this.mouseLeaveTimer = null;
                             }
                             if (this.currentValue !== this.originalValue) {
                                 $wire.updateField('description', this.currentValue).then(() => {
                                     this.originalValue = this.currentValue;
                                     this.editing = false;
                                 });
                             } else {
                                 this.editing = false;
                             }
                         }
                     }"
                     @mouseenter="handleMouseEnter()"
                     @mouseleave="handleMouseLeave()"
                >
                    <div class="mb-2">
                        <flux:heading size="sm">Description</flux:heading>
                    </div>
                    <div x-show="editing" x-cloak>
                        <flux:textarea
                            x-ref="input"
                            x-model="currentValue"
                            x-on:input="handleInput()"
                            @keydown.enter="saveIfChanged()"
                            @keydown.escape="cancelEditing()"
                            rows="4"
                        />
                        @error('description')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div x-show="!editing" @click="startEditing()" class="cursor-pointer">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400" x-text="originalValue || 'No description provided.'"></p>
                    </div>
                </div>

                <!-- Project Details Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Start Date -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($project->start_date?->format('Y-m-d') ?? ''),
                             currentValue: @js($project->start_date?->format('Y-m-d') ?? ''),
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.startDate = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.startDate = this.originalValue;
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             },
                             handleMouseLeave() {
                                 if (!this.editing) return;
                                 this.mouseLeaveTimer = setTimeout(() => {
                                     this.saveIfChanged();
                                 }, 300);
                             },
                             handleMouseEnter() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             },
                             saveIfChanged() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                                 if (this.currentValue !== this.originalValue) {
                                     $wire.updateField('startDate', this.currentValue).then(() => {
                                         this.originalValue = this.currentValue;
                                     });
                                 } else {
                                     this.editing = false;
                                 }
                             }
                         }"
                         @mouseenter="handleMouseEnter()"
                         @mouseleave="handleMouseLeave()"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">Start Date</flux:heading>
                            <button
                                x-show="!editing"
                                @click="startEditing()"
                                class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                                type="button"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        </div>
                        <div x-show="editing" x-cloak>
                            <flux:input
                                x-ref="input"
                                x-model="currentValue"
                                x-on:input="$wire.startDate = currentValue"
                                wire:model.live="startDate"
                                @keydown.enter="saveIfChanged()"
                                @keydown.escape="cancelEditing()"
                                type="date"
                            />
                            @error('startDate')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                {{ $project->start_date?->format('M j, Y') ?? 'Not set' }}
                            </p>
                        </div>
                    </div>

                    <!-- End Date -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($project->end_date?->format('Y-m-d') ?? ''),
                             currentValue: @js($project->end_date?->format('Y-m-d') ?? ''),
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.endDate = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.endDate = this.originalValue;
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             },
                             handleMouseLeave() {
                                 if (!this.editing) return;
                                 this.mouseLeaveTimer = setTimeout(() => {
                                     this.saveIfChanged();
                                 }, 300);
                             },
                             handleMouseEnter() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             },
                             saveIfChanged() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                                 if (this.currentValue !== this.originalValue) {
                                     $wire.updateField('endDate', this.currentValue).then(() => {
                                         this.originalValue = this.currentValue;
                                     });
                                 } else {
                                     this.editing = false;
                                 }
                             }
                         }"
                         @mouseenter="handleMouseEnter()"
                         @mouseleave="handleMouseLeave()"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">End Date</flux:heading>
                            <button
                                x-show="!editing"
                                @click="startEditing()"
                                class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                                type="button"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        </div>
                        <div x-show="editing" x-cloak>
                            <flux:input
                                x-ref="input"
                                x-model="currentValue"
                                x-on:input="$wire.endDate = currentValue"
                                wire:model.live="endDate"
                                @keydown.enter="saveIfChanged()"
                                @keydown.escape="cancelEditing()"
                                type="date"
                            />
                            @error('endDate')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                {{ $project->end_date?->format('M j, Y') ?? 'Not set' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Progress -->
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

                <!-- Associated Tasks -->
                @if($project->tasks->isNotEmpty())
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
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded ml-3 {{ $task->status->badgeColor() }}">
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
                @if($project->tags->isNotEmpty())
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
                @if($project->reminders->isNotEmpty())
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
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    <flux:heading size="sm">Collaboration</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                        Collaboration features coming soon...
                    </p>
                </div>

                <!-- Delete Button -->
                <div class="flex justify-end mt-6 mb-4">
                    <flux:button
                        variant="danger"
                        @click="$wire.showDeleteConfirm = true"
                    >
                        Delete Project
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteConfirm" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Delete Project</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
            Are you sure you want to delete "<strong>{{ $project?->name }}</strong>"? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" @click="$wire.showDeleteConfirm = false">
                Cancel
            </flux:button>
            <flux:button variant="danger" wire:click="deleteProject">
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

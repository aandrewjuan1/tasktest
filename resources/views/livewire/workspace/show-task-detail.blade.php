<?php

use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $isOpen = false;

    public ?Task $task = null;

    public bool $showDeleteConfirm = false;

    // Edit fields
    public string $title = '';

    public string $description = '';

    public string $status = '';

    public string $priority = '';

    public string $complexity = '';

    public string $duration = '';

    public string $startDatetime = '';

    public string $endDatetime = '';

    public string $projectId = '';

    public string $eventId = '';

    #[On('view-task-detail')]
    public function openModal(int $id): void
    {
        // Load task first before setting isOpen to prevent DOM flicker
        $this->task = Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions'])
            ->findOrFail($id);

        $this->authorize('view', $this->task);

        // Load data before opening modal to ensure stable DOM structure
        $this->loadTaskData();

        // Set modal state after data is loaded
        $this->isOpen = true;
        $this->showDeleteConfirm = false;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->task = null;
        $this->showDeleteConfirm = false;
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
        $this->startDatetime = $this->task->start_datetime?->format('Y-m-d\TH:i') ?? '';
        $this->endDatetime = $this->task->end_datetime?->format('Y-m-d\TH:i') ?? '';
        $this->projectId = (string) ($this->task->project_id ?? '');
        $this->eventId = (string) ($this->task->event_id ?? '');
    }

    public function updateField(string $field, mixed $value): void
    {
        if (! $this->task) {
            return;
        }

        $this->authorize('update', $this->task);

        $validationRules = match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:to_do,doing,done'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'complexity' => ['nullable', 'string', 'in:simple,moderate,complex'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'startDatetime' => ['nullable', 'date'],
            'endDatetime' => ['nullable', 'date'],
            'projectId' => ['nullable', 'exists:projects,id'],
            default => [],
        };

        if (empty($validationRules)) {
            return;
        }

        $this->validate([
            $field => $validationRules,
        ]);

        try {
            DB::transaction(function () use ($field, $value) {
                $updateData = [];

                switch ($field) {
                    case 'title':
                        $updateData['title'] = $value;
                        break;
                    case 'description':
                        $updateData['description'] = $value ?: null;
                        break;
                    case 'status':
                        $updateData['status'] = $value ?: null;
                        break;
                    case 'priority':
                        $updateData['priority'] = $value ?: null;
                        break;
                    case 'complexity':
                        $updateData['complexity'] = $value ?: null;
                        break;
                    case 'duration':
                        $updateData['duration'] = $value ?: null;
                        break;
                    case 'startDatetime':
                        $updateData['start_datetime'] = $value ? \Carbon\Carbon::parse($value) : null;
                        break;
                    case 'endDatetime':
                        $updateData['end_datetime'] = $value ? \Carbon\Carbon::parse($value) : null;
                        break;
                    case 'projectId':
                        $updateData['project_id'] = $value ?: null;
                        break;
                }

                $this->task->update($updateData);
                $this->task->refresh();
            });

            $this->loadTaskData();
            $this->dispatch('task-updated');
            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update task field', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'task_id' => $this->task->id,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update task. Please try again.', type: 'error');
        }
    }

    public function confirmDelete(): void
    {
        $this->showDeleteConfirm = true;
    }

    public function deleteTask(): void
    {
        $this->authorize('delete', $this->task);

        try {
            DB::transaction(function () {
                $this->task->delete();
            });

            $this->closeModal();
            $this->dispatch('task-deleted');
            $this->dispatch('notify', message: 'Task deleted successfully', type: 'success');
            session()->flash('message', 'Task deleted successfully!');
        } catch (\Exception $e) {
            \Log::error('Failed to delete task', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $this->task->id,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete task. Please try again.', type: 'error');
        }
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::accessibleBy(auth()->user())
            ->orderBy('name')
            ->get();
    }
}; ?>

<div wire:key="task-detail-modal">
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout" closeable="false">
        <div class="space-y-6" wire:key="task-content-{{ $task?->id ?? 'empty' }}">
            @if($task)
                <!-- Header -->
                <div>
                    <div class="flex-1"
                         x-data="{
                             editing: false,
                             originalValue: @js($task->title),
                             currentValue: @js($task->title),
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.title = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             save() {
                                 if (this.currentValue !== this.originalValue) {
                                     $wire.updateField('title', this.currentValue)
                                         .then(() => {
                                             this.originalValue = this.currentValue;
                                             this.editing = false;
                                         })
                                         .catch(() => {
                                             this.currentValue = this.originalValue;
                                             $wire.title = this.originalValue;
                                             this.editing = false;
                                             window.dispatchEvent(new CustomEvent('notify', {
                                                 detail: {
                                                     message: 'Failed to update title. Please try again.',
                                                     type: 'error',
                                                 },
                                             }));
                                         });
                                 } else {
                                     this.editing = false;
                                 }
                             },
                             cancel() {
                                 this.currentValue = this.originalValue;
                                 $wire.title = this.originalValue;
                                 this.editing = false;
                             }
                         }"
                    >
                        <div x-show="!editing" class="flex items-center gap-2">
                            <flux:heading size="lg" x-text="originalValue" class="cursor-pointer" @click="startEditing()"></flux:heading>
                            <button
                                @click="startEditing()"
                                class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                                type="button"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        </div>
                        <div x-show="editing" x-cloak @click.away="cancel()" class="space-y-2">
                            <div class="flex items-start gap-2">
                                <div class="flex-1">
                                    <flux:input
                                        x-ref="input"
                                        x-model="currentValue"
                                        x-on:input="$wire.title = currentValue"
                                        @keydown.enter.prevent="save()"
                                        @keydown.escape="cancel()"
                                        label="Title"
                                        required
                                    />
                                </div>
                                <div class="flex items-center gap-2 pt-6">
                                    <button
                                        @click="save()"
                                        class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-lg transition-colors"
                                    >
                                        Done
                                    </button>
                                    <button
                                        @click="cancel()"
                                        class="px-3 py-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-lg transition-colors"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            @error('title')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div
                     x-data="{
                         editing: false,
                         originalValue: @js($task->description ?? ''),
                         currentValue: @js($task->description ?? ''),
                         startEditing() {
                             this.editing = true;
                             this.currentValue = this.originalValue;
                             $wire.description = this.originalValue;
                             $nextTick(() => $refs.input?.focus());
                         },
                         save() {
                             if (this.currentValue !== this.originalValue) {
                                $wire.updateField('description', this.currentValue)
                                    .then(() => {
                                        this.originalValue = this.currentValue;
                                        this.editing = false;
                                    })
                                    .catch(() => {
                                        this.currentValue = this.originalValue;
                                        $wire.description = this.originalValue;
                                        this.editing = false;
                                        window.dispatchEvent(new CustomEvent('notify', {
                                            detail: {
                                                message: 'Failed to update description. Please try again.',
                                                type: 'error',
                                            },
                                        }));
                                    });
                             } else {
                                 this.editing = false;
                             }
                         },
                         cancel() {
                             this.currentValue = this.originalValue;
                             $wire.description = this.originalValue;
                             this.editing = false;
                         }
                     }"
                >
                    <div class="mb-2">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">Description</flux:heading>
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
                    </div>
                    <div x-show="editing" x-cloak @click.away="cancel()" class="space-y-2">
                        <flux:textarea
                            x-ref="input"
                            x-model="currentValue"
                            x-on:input="$wire.description = currentValue"
                            @keydown.ctrl.enter.prevent="save()"
                            @keydown.meta.enter.prevent="save()"
                            @keydown.escape="cancel()"
                            rows="4"
                        />
                        <div class="flex items-center gap-2">
                            <button
                                @click="save()"
                                class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-lg transition-colors"
                            >
                                Done
                            </button>
                            <button
                                @click="cancel()"
                                class="px-3 py-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                        @error('description')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div x-show="!editing" @click="startEditing()" class="cursor-pointer">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400" x-text="originalValue || 'No description provided.'"></p>
                    </div>
                </div>

                <!-- Task Details Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Status -->
                    <x-inline-edit-dropdown
                        label="Status"
                        field="status"
                        :value="$task->status?->value ?? 'to_do'"
                    >
                        <x-slot:trigger>
                            <span class="text-sm font-medium">
                                {{ match($task->status?->value ?? 'to_do') {
                                    'to_do' => 'To Do',
                                    'doing' => 'In Progress',
                                    'done' => 'Done',
                                    default => 'To Do'
                                } }}
                            </span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select('to_do')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($task->status?->value ?? 'to_do') === 'to_do' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                To Do
                            </button>
                            <button
                                @click="select('doing')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($task->status?->value ?? 'to_do') === 'doing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                In Progress
                            </button>
                            <button
                                @click="select('done')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($task->status?->value ?? 'to_do') === 'done' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Done
                            </button>
                        </x-slot:options>
                    </x-inline-edit-dropdown>

                    <!-- Duration -->
                    <x-inline-edit-dropdown
                        label="Duration"
                        field="duration"
                        :value="$task->duration"
                    >
                        <x-slot:trigger>
                            <span class="text-sm font-medium">
                                @if($task->duration)
                                    @php
                                        $duration = (int) $task->duration;
                                        if ($duration >= 60) {
                                            $hours = floor($duration / 60);
                                            $minutes = $duration % 60;
                                            if ($minutes === 0) {
                                                $display = $hours . ($hours === 1 ? ' hour' : ' hours');
                                            } else {
                                                $display = $hours . ($hours === 1 ? ' hour' : ' hours') . ' ' . $minutes . ($minutes === 1 ? ' minute' : ' minutes');
                                            }
                                        } else {
                                            $display = $duration . ($duration === 1 ? ' minute' : ' minutes');
                                        }
                                    @endphp
                                    {{ $display }}
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">Not set</span>
                                @endif
                            </span>
                        </x-slot:trigger>

                        <x-slot:options>
                            @foreach([15, 30, 45, 60, 90, 120, 180, 240, 300] as $minutes)
                                <button
                                    @click="select({{ $minutes }})"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ (int) $task->duration === $minutes ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                                >
                                    {{ $minutes }} minutes
                                </button>
                            @endforeach
                            <button
                                @click="select(null)"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->duration === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Clear
                            </button>
                        </x-slot:options>
                    </x-inline-edit-dropdown>

                    <!-- Start Datetime -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($task->start_datetime?->format('Y-m-d\TH:i') ?? ''),
                             currentValue: @js($task->start_datetime?->format('Y-m-d\TH:i') ?? ''),
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.startDatetime = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.startDatetime = this.originalValue;
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
                                    $wire.updateField('startDatetime', this.currentValue)
                                        .then(() => {
                                            this.originalValue = this.currentValue;
                                        })
                                        .catch(() => {
                                            this.currentValue = this.originalValue;
                                            $wire.startDatetime = this.originalValue;
                                            this.editing = false;
                                            window.dispatchEvent(new CustomEvent('notify', {
                                                detail: {
                                                    message: 'Failed to update start date & time. Please try again.',
                                                    type: 'error',
                                                },
                                            }));
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
                            <flux:heading size="sm">Start Date & Time</flux:heading>
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
                                x-on:input="$wire.startDatetime = currentValue"
                                wire:model.live="startDatetime"
                                @keydown.enter="saveIfChanged()"
                                @keydown.escape="cancelEditing()"
                                type="datetime-local"
                            />
                            @error('startDatetime')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                @if($task->start_datetime)
                                    @php
                                        $manilaTime = $task->start_datetime->setTimezone('Asia/Manila');
                                    @endphp
                                    {{ $manilaTime->format('M j, Y \a\t g:i A') }}
                                @else
                                    Not set
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- End Datetime -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($task->end_datetime?->format('Y-m-d\TH:i') ?? ''),
                             currentValue: @js($task->end_datetime?->format('Y-m-d\TH:i') ?? ''),
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.endDatetime = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.endDatetime = this.originalValue;
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
                                    $wire.updateField('endDatetime', this.currentValue)
                                        .then(() => {
                                            this.originalValue = this.currentValue;
                                        })
                                        .catch(() => {
                                            this.currentValue = this.originalValue;
                                            $wire.endDatetime = this.originalValue;
                                            this.editing = false;
                                            window.dispatchEvent(new CustomEvent('notify', {
                                                detail: {
                                                    message: 'Failed to update due date & time. Please try again.',
                                                    type: 'error',
                                                },
                                            }));
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
                            <flux:heading size="sm">Due Date & Time</flux:heading>
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
                                x-on:input="$wire.endDatetime = currentValue"
                                wire:model.live="endDatetime"
                                @keydown.enter="saveIfChanged()"
                                @keydown.escape="cancelEditing()"
                                type="datetime-local"
                            />
                            @error('endDatetime')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2 {{ $task->end_datetime?->isPast() && $task->status->value !== 'done' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                @if($task->end_datetime)
                                    @php
                                        $manilaTime = $task->end_datetime->setTimezone('Asia/Manila');
                                    @endphp
                                    {{ $manilaTime->format('M j, Y \a\t g:i A') }}
                                @else
                                    Not set
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Project -->
                <x-inline-edit-dropdown
                    label="Project"
                    field="projectId"
                    :value="$task->project_id"
                >
                    <x-slot:trigger>
                        <span class="text-sm font-medium">
                            @if($task->project)
                                <span class="text-blue-600 dark:text-blue-400">{{ $task->project->name }}</span>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">Not assigned to a project</span>
                            @endif
                        </span>
                    </x-slot:trigger>

                    <x-slot:options>
                        @foreach($this->projects as $project)
                            <button
                                wire:key="project-{{ $project->id }}"
                                @click="select('{{ $project->id }}')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->project_id === $project->id ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                {{ $project->name }}
                            </button>
                        @endforeach
                        @if($this->projects->isEmpty())
                            <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No projects available</div>
                        @endif
                    </x-slot:options>
                </x-inline-edit-dropdown>

                <!-- Tags -->
                @if($task->tags->isNotEmpty())
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
                @if($task->reminders->isNotEmpty())
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
                @if($task->pomodoroSessions->isNotEmpty())
                    <div>
                        <flux:heading size="sm">Pomodoro Sessions</flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                            {{ $task->pomodoroSessions->count() }} session(s) completed
                        </p>
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
                <div class="flex justify-end mt-6 mb-4" x-data="{}">
                    <flux:button
                        variant="danger"
                        @click="$wire.showDeleteConfirm = true"
                    >
                        Delete Task
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteConfirm" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Delete Task</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
            Are you sure you want to delete "<strong>{{ $task?->title }}</strong>"? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" @click="$wire.showDeleteConfirm = false">
                Cancel
            </flux:button>
            <flux:button
                variant="danger"
                x-data="{}"
                @click="
                    $dispatch('optimistic-item-deleted', {
                        itemId: '{{ $task?->id }}',
                        itemType: 'task',
                    });
                    $wire.deleteTask();
                "
            >
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

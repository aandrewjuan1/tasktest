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
        $this->showDeleteConfirm = false;
        $this->loadTaskData();
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
        $this->startDate = $this->task->start_date?->format('Y-m-d') ?? '';
        $this->startTime = $this->task->start_time ? substr($this->task->start_time, 0, 5) : '';
        $this->endDate = $this->task->end_date?->format('Y-m-d') ?? '';
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
            'startDate' => ['nullable', 'date'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'endDate' => ['nullable', 'date'],
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
                    case 'startDate':
                        $updateData['start_date'] = $value ?: null;
                        break;
                    case 'startTime':
                        $updateData['start_time'] = $value ? $value.':00' : null;
                        break;
                    case 'endDate':
                        $updateData['end_date'] = $value ?: null;
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

<div>
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout" closeable="false">
        @if($task)
            <div class="space-y-6">
                <!-- Header -->
                <div>
                    <div class="flex-1"
                         x-data="{
                             editing: false,
                             originalValue: @js($task->title),
                             currentValue: @js($task->title),
                             debounceTimer: null,
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.title = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.title = this.originalValue;
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
                                 $wire.title = this.currentValue;
                                 if (this.debounceTimer) {
                                     clearTimeout(this.debounceTimer);
                                 }
                                 this.debounceTimer = setTimeout(() => {
                                     if (this.currentValue !== this.originalValue) {
                                         $wire.updateField('title', this.currentValue).then(() => {
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
                                     $wire.updateField('title', this.currentValue).then(() => {
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
                                label="Title"
                                required
                            />
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

                <!-- Task Details Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Status -->
                    <div class="relative" @click.away="openDropdown = null"
                         x-data="{
                             openDropdown: null,
                             mouseLeaveTimer: null,
                             toggleDropdown() {
                                 this.openDropdown = this.openDropdown === 'status' ? null : 'status';
                             },
                             isOpen() {
                                 return this.openDropdown === 'status';
                             },
                             selectStatus(value) {
                                 $wire.updateField('status', value).then(() => {
                                     this.openDropdown = null;
                                 });
                             },
                             handleMouseLeave() {
                                 this.mouseLeaveTimer = setTimeout(() => {
                                     this.openDropdown = null;
                                 }, 300);
                             },
                             handleMouseEnter() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             }
                         }"
                         @mouseenter="handleMouseEnter()"
                         @mouseleave="handleMouseLeave()"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">Status</flux:heading>
                        </div>
                        <button
                            type="button"
                            @click.stop="toggleDropdown()"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors w-full"
                        >
                            <span class="text-sm font-medium">
                                {{ match($task->status?->value ?? 'to_do') {
                                    'to_do' => 'To Do',
                                    'doing' => 'In Progress',
                                    'done' => 'Done',
                                    default => 'To Do'
                                } }}
                            </span>
                        </button>
                        <div
                            x-show="isOpen()"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                @click="selectStatus('to_do')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($task->status?->value ?? 'to_do') === 'to_do' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                To Do
                            </button>
                            <button
                                @click="selectStatus('doing')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($task->status?->value ?? 'to_do') === 'doing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                In Progress
                            </button>
                            <button
                                @click="selectStatus('done')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($task->status?->value ?? 'to_do') === 'done' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Done
                            </button>
                        </div>
                        @error('status')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Priority -->
                    <div class="relative" @click.away="openDropdown = null"
                         x-data="{
                             openDropdown: null,
                             mouseLeaveTimer: null,
                             toggleDropdown() {
                                 this.openDropdown = this.openDropdown === 'priority' ? null : 'priority';
                             },
                             isOpen() {
                                 return this.openDropdown === 'priority';
                             },
                             selectPriority(value) {
                                 $wire.updateField('priority', value).then(() => {
                                     this.openDropdown = null;
                                 });
                             },
                             handleMouseLeave() {
                                 this.mouseLeaveTimer = setTimeout(() => {
                                     this.openDropdown = null;
                                 }, 300);
                             },
                             handleMouseEnter() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             }
                         }"
                         @mouseenter="handleMouseEnter()"
                         @mouseleave="handleMouseLeave()"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">Priority</flux:heading>
                        </div>
                        <button
                            type="button"
                            @click.stop="toggleDropdown()"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors w-full"
                        >
                            <span class="text-sm font-medium">
                                @if($task->priority)
                                    <span class="inline-flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full {{ $task->priority->dotColor() }}"></span>
                                        <span>{{ ucfirst($task->priority->value) }}</span>
                                    </span>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">Not set</span>
                                @endif
                            </span>
                        </button>
                        <div
                            x-show="isOpen()"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                @click="selectPriority('low')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->priority?->value === 'low' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Low
                            </button>
                            <button
                                @click="selectPriority('medium')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->priority?->value === 'medium' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Medium
                            </button>
                            <button
                                @click="selectPriority('high')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->priority?->value === 'high' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                High
                            </button>
                            <button
                                @click="selectPriority('urgent')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->priority?->value === 'urgent' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Urgent
                            </button>
                        </div>
                        @error('priority')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Complexity -->
                    <div class="relative" @click.away="openDropdown = null"
                         x-data="{
                             openDropdown: null,
                             mouseLeaveTimer: null,
                             toggleDropdown() {
                                 this.openDropdown = this.openDropdown === 'complexity' ? null : 'complexity';
                             },
                             isOpen() {
                                 return this.openDropdown === 'complexity';
                             },
                             selectComplexity(value) {
                                 $wire.updateField('complexity', value).then(() => {
                                     this.openDropdown = null;
                                 });
                             },
                             handleMouseLeave() {
                                 this.mouseLeaveTimer = setTimeout(() => {
                                     this.openDropdown = null;
                                 }, 300);
                             },
                             handleMouseEnter() {
                                 if (this.mouseLeaveTimer) {
                                     clearTimeout(this.mouseLeaveTimer);
                                     this.mouseLeaveTimer = null;
                                 }
                             }
                         }"
                         @mouseenter="handleMouseEnter()"
                         @mouseleave="handleMouseLeave()"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">Complexity</flux:heading>
                        </div>
                        <button
                            type="button"
                            @click.stop="toggleDropdown()"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors w-full"
                        >
                            <span class="text-sm font-medium">
                                @if($task->complexity)
                                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded {{ $task->complexity->badgeColor() }}">
                                        {{ ucfirst($task->complexity->value) }}
                                    </span>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">Not set</span>
                                @endif
                            </span>
                        </button>
                        <div
                            x-show="isOpen()"
                            x-cloak
                            x-transition
                            class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
                        >
                            <button
                                @click="selectComplexity('simple')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->complexity?->value === 'simple' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Simple
                            </button>
                            <button
                                @click="selectComplexity('moderate')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->complexity?->value === 'moderate' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Moderate
                            </button>
                            <button
                                @click="selectComplexity('complex')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->complexity?->value === 'complex' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Complex
                            </button>
                        </div>
                        @error('complexity')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Duration -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($task->duration ? (string)$task->duration : ''),
                             currentValue: @js($task->duration ? (string)$task->duration : ''),
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.duration = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.duration = this.originalValue;
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
                                     $wire.updateField('duration', this.currentValue).then(() => {
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
                            <flux:heading size="sm">Duration</flux:heading>
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
                                x-on:input="$wire.duration = currentValue"
                                wire:model.live="duration"
                                @keydown.enter="saveIfChanged()"
                                @keydown.escape="cancelEditing()"
                                type="number"
                                min="1"
                            />
                            @error('duration')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
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
                                    Not set
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Start Date -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($task->start_date?->format('Y-m-d') ?? ''),
                             currentValue: @js($task->start_date?->format('Y-m-d') ?? ''),
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
                                {{ $task->start_date?->format('M j, Y') ?? 'Not set' }}
                                @if($task->start_time && $task->start_date)
                                    @php
                                        $dateString = $task->start_date instanceof \Carbon\Carbon
                                            ? $task->start_date->format('Y-m-d')
                                            : $task->start_date;
                                        $startDateTime = Carbon::parse($dateString . ' ' . $task->start_time, 'UTC');
                                        $manilaTime = $startDateTime->setTimezone('Asia/Manila');
                                    @endphp
                                    <span class="text-zinc-500 dark:text-zinc-400">at {{ $manilaTime->format('g:i A') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- End Date -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($task->end_date?->format('Y-m-d') ?? ''),
                             currentValue: @js($task->end_date?->format('Y-m-d') ?? ''),
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
                            <flux:heading size="sm">Due Date</flux:heading>
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
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2 {{ $task->end_date?->isPast() && $task->status->value !== 'done' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                {{ $task->end_date?->format('M j, Y') ?? 'Not set' }}
                            </p>
                        </div>
                    </div>

                    <!-- Start Time -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($task->start_time ? substr($task->start_time, 0, 5) : ''),
                             currentValue: @js($task->start_time ? substr($task->start_time, 0, 5) : ''),
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.startTime = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.startTime = this.originalValue;
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
                                     $wire.updateField('startTime', this.currentValue).then(() => {
                                         this.originalValue = this.currentValue;
                                     });
                                 } else {
                                     this.editing = false;
                                 }
                             }
                         }"
                         @mouseenter="handleMouseEnter()"
                         @mouseleave="handleMouseLeave()"
                         x-show="$wire.startTime || editing"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">Start Time</flux:heading>
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
                                x-on:input="$wire.startTime = currentValue"
                                wire:model.live="startTime"
                                @keydown.enter="saveIfChanged()"
                                @keydown.escape="cancelEditing()"
                                type="time"
                            />
                            @error('startTime')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                @if($task->start_time && $task->start_date)
                                    @php
                                        $dateString = $task->start_date instanceof \Carbon\Carbon
                                            ? $task->start_date->format('Y-m-d')
                                            : $task->start_date;
                                        $startDateTime = Carbon::parse($dateString . ' ' . $task->start_time, 'UTC');
                                        $manilaTime = $startDateTime->setTimezone('Asia/Manila');
                                    @endphp
                                    {{ $manilaTime->format('g:i A') }}
                                @else
                                    Not set
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Project -->
                <div class="relative" @click.away="openDropdown = null"
                     x-data="{
                         openDropdown: null,
                         mouseLeaveTimer: null,
                         toggleDropdown() {
                             this.openDropdown = this.openDropdown === 'project' ? null : 'project';
                         },
                         isOpen() {
                             return this.openDropdown === 'project';
                         },
                         selectProject(value) {
                             $wire.updateField('projectId', value).then(() => {
                                 this.openDropdown = null;
                             });
                         },
                         handleMouseLeave() {
                             this.mouseLeaveTimer = setTimeout(() => {
                                 this.openDropdown = null;
                             }, 300);
                         },
                         handleMouseEnter() {
                             if (this.mouseLeaveTimer) {
                                 clearTimeout(this.mouseLeaveTimer);
                                 this.mouseLeaveTimer = null;
                             }
                         }
                     }"
                     @mouseenter="handleMouseEnter()"
                     @mouseleave="handleMouseLeave()"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <flux:heading size="sm">Project</flux:heading>
                    </div>
                    <button
                        type="button"
                        @click.stop="toggleDropdown()"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors w-full"
                    >
                        <span class="text-sm font-medium">
                            @if($task->project)
                                <span class="text-blue-600 dark:text-blue-400">{{ $task->project->name }}</span>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">Not assigned to a project</span>
                            @endif
                        </span>
                    </button>
                    <div
                        x-show="isOpen()"
                        x-cloak
                        x-transition
                        class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 max-h-60 overflow-y-auto"
                    >
                        @foreach($this->projects as $project)
                            <button
                                wire:key="project-{{ $project->id }}"
                                @click="selectProject('{{ $project->id }}')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $task->project_id === $project->id ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                {{ $project->name }}
                            </button>
                        @endforeach
                        @if($this->projects->isEmpty())
                            <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No projects available</div>
                        @endif
                    </div>
                    @error('projectId')
                        <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

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
                <div class="flex justify-end mt-6 mb-4">
                    <flux:button
                        variant="danger"
                        @click="$wire.showDeleteConfirm = true"
                    >
                        Delete Task
                    </flux:button>
                </div>
            </div>
        @endif
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
            <flux:button variant="danger" wire:click="deleteTask">
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

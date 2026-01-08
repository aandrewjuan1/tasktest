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

    // All task update mutations are handled by the parent `show-items` component.

    public function confirmDelete(): void
    {
        $this->showDeleteConfirm = true;
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
                                     this.editing = false;

                                     $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                                         taskId: {{ $task->id }},
                                         field: 'title',
                                         value: this.currentValue,
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
                                this.editing = false;

                                $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                                    taskId: {{ $task->id }},
                                    field: 'description',
                                    value: this.currentValue,
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
                        :item-id="$task->id"
                        :use-parent="true"
                        :value="$task->status?->value ?? 'to_do'"
                    >
                        <x-slot:trigger>
                            <span
                                class="text-sm font-medium"
                                x-text="{
                                    to_do: 'To Do',
                                    doing: 'In Progress',
                                    done: 'Done'
                                }[selectedValue || 'to_do']"
                            ></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select('to_do')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'to_do' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                To Do
                            </button>
                            <button
                                @click="select('doing')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'doing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                In Progress
                            </button>
                            <button
                                @click="select('done')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'done' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Done
                            </button>
                        </x-slot:options>
                    </x-inline-edit-dropdown>

                    <!-- Duration -->
                    <x-inline-edit-dropdown
                        label="Duration"
                        field="duration"
                        :item-id="$task->id"
                        :use-parent="true"
                        :value="$task->duration"
                    >
                        <x-slot:trigger>
                            <span class="text-sm font-medium" x-text="selectedValue ? formatDuration(selectedValue) : 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            @foreach([15, 30, 45, 60, 90, 120, 180, 240, 300] as $minutes)
                                <button
                                    @click="select({{ $minutes }})"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    :class="selectedValue == {{ $minutes }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                >
                                    {{ $minutes }} minutes
                                </button>
                            @endforeach
                        </x-slot:options>
                    </x-inline-edit-dropdown>

                    <!-- Priority -->
                    <x-inline-edit-dropdown
                        label="Priority"
                        field="priority"
                        :item-id="$task->id"
                        :use-parent="true"
                        :value="$task->priority?->value ?? ''"
                    >
                        <x-slot:trigger>
                            <span
                                class="text-sm font-medium"
                                x-text="{
                                    low: 'Low',
                                    medium: 'Medium',
                                    high: 'High',
                                    urgent: 'Urgent'
                                }[selectedValue || ''] || 'Not set'"
                            ></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select('low')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'low' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Low
                            </button>
                            <button
                                @click="select('medium')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'medium' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Medium
                            </button>
                            <button
                                @click="select('high')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'high' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                High
                            </button>
                            <button
                                @click="select('urgent')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'urgent' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Urgent
                            </button>
                        </x-slot:options>
                    </x-inline-edit-dropdown>

                    <!-- Complexity -->
                    <x-inline-edit-dropdown
                        label="Complexity"
                        field="complexity"
                        :item-id="$task->id"
                        :use-parent="true"
                        :value="$task->complexity?->value ?? ''"
                    >
                        <x-slot:trigger>
                            <span
                                class="text-sm font-medium"
                                x-text="{
                                    simple: 'Simple',
                                    moderate: 'Moderate',
                                    complex: 'Complex'
                                }[selectedValue || ''] || 'Not set'"
                            ></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select('simple')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'simple' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Simple
                            </button>
                            <button
                                @click="select('moderate')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'moderate' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Moderate
                            </button>
                            <button
                                @click="select('complex')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue === 'complex' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Complex
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
                                    this.editing = false;

                                    $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                                        taskId: {{ $task->id }},
                                        field: 'startDatetime',
                                        value: this.currentValue,
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
                                    this.editing = false;

                                    $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                                        taskId: {{ $task->id }},
                                        field: 'endDatetime',
                                        value: this.currentValue,
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
                <div x-data="{ projects: @js($this->projects->pluck('name', 'id')->toArray()) }">
                    <x-inline-edit-dropdown
                        label="Project"
                        field="projectId"
                        :item-id="$task->id"
                        :use-parent="true"
                        :value="$task->project_id ? (string) $task->project_id : ''"
                    >
                        <x-slot:trigger>
                            <span class="text-sm font-medium">
                                <span
                                    x-show="selectedValue && projects[String(selectedValue)]"
                                    class="text-blue-600 dark:text-blue-400"
                                    x-text="projects[String(selectedValue)]"
                                ></span>
                                <span
                                    x-show="!selectedValue || !projects[String(selectedValue)]"
                                    class="text-zinc-500 dark:text-zinc-400"
                                >Not assigned to a project</span>
                            </span>
                        </x-slot:trigger>

                    <x-slot:options>
                        <button
                            @click="select(null)"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            :class="selectedValue === null || selectedValue === '' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                        >
                            None
                        </button>
                        @foreach($this->projects as $project)
                            <button
                                wire:key="project-{{ $project->id }}"
                                @click="select('{{ $project->id }}')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="selectedValue == '{{ $project->id }}' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                {{ $project->name }}
                            </button>
                        @endforeach
                        @if($this->projects->isEmpty())
                            <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No projects available</div>
                        @endif
                    </x-slot:options>
                    </x-inline-edit-dropdown>
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
                    const taskId = {{ $task?->id ?? 'null' }};
                    if (taskId) {
                        $wire.showDeleteConfirm = false;
                        $wire.isOpen = false;
                        $wire.$dispatchTo('workspace.show-items', 'delete-task', {
                            taskId: taskId,
                        });
                    }
                "
            >
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

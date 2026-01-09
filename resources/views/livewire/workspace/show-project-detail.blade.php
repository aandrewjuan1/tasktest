<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Tag;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public bool $isOpen = false;
    public ?Project $project = null;
    public bool $showDeleteConfirm = false;

    // Edit fields
    public string $name = '';
    public string $description = '';
    public string $startDatetime = '';
    public string $endDatetime = '';

    #[On('view-project-detail')]
    public function openModal(int $id): void
    {
        // Load project first before setting isOpen to prevent DOM flicker
        $this->project = Project::with(['tags', 'tasks', 'reminders'])
            ->findOrFail($id);

        $this->authorize('view', $this->project);

        // Load data before opening modal to ensure stable DOM structure
        $this->loadProjectData();

        // Set modal state after data is loaded
        $this->isOpen = true;
        $this->showDeleteConfirm = false;
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
        $this->startDatetime = $this->project->start_datetime ? $this->project->start_datetime->format('Y-m-d\TH:i') : '';
        $this->endDatetime = $this->project->end_datetime ? $this->project->end_datetime->format('Y-m-d\TH:i') : '';
    }

    public function updateField(string $field, mixed $value): void
    {
        // Intentionally left blank; updates are handled by the parent show-items component.
    }

    public function confirmDelete(): void
    {
        $this->showDeleteConfirm = true;
    }

    public function deleteProject(): void
    {
        // Intentionally left blank; deletes are handled by the parent show-items component.
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

<div wire:key="project-detail-modal">
    <flux:modal
        wire:model="isOpen"
        class="min-w-[700px] border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl bg-white/95 dark:bg-zinc-900/95"
        variant="flyout"
        closeable="false"
    >
        <div class="space-y-6 px-6 py-5" wire:key="project-content-{{ $project?->id ?? 'empty' }}">
            @if($project)
                <!-- Header -->
                <div class="border-b border-zinc-200 dark:border-zinc-800 pb-4">
                    <div class="flex-1"
                         x-data="{
                             editing: false,
                             originalValue: @js($project->name),
                             currentValue: @js($project->name),
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.name = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             save() {
                                 if (this.currentValue !== this.originalValue) {
                                    this.editing = false;

                                    $wire.$dispatchTo('workspace.show-items', 'update-project-field', {
                                        projectId: {{ $project->id }},
                                        field: 'name',
                                        value: this.currentValue,
                                    });
                                 } else {
                                     this.editing = false;
                                 }
                             },
                             cancel() {
                                 this.currentValue = this.originalValue;
                                 $wire.name = this.originalValue;
                                 this.editing = false;
                             }
                         }"
                    >
                        <div x-show="!editing" class="flex items-center gap-2">
                            <flux:heading
                                size="xl"
                                x-text="originalValue"
                                class="cursor-pointer text-zinc-900 dark:text-zinc-50 tracking-tight"
                                @click="startEditing()"
                            ></flux:heading>
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
                                        x-on:input="$wire.name = currentValue"
                                        @keydown.enter.prevent="save()"
                                        @keydown.escape="cancel()"
                                        label="Name"
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
                            @error('name')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @php
                        $progress = $this->progress;
                    @endphp

                    <!-- Key meta pills -->
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <!-- Progress -->
                        <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h10M4 14h6M4 18h4" />
                            </svg>
                            <span class="font-medium">
                                {{ $progress['percentage'] }}% complete
                            </span>
                        </div>

                        <!-- Task count -->
                        <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h12M9 12h12M9 19h12M4 5h.01M4 12h.01M4 19h.01" />
                            </svg>
                            <span class="font-medium">
                                {{ $progress['completed'] }} / {{ $progress['total'] }} tasks
                            </span>
                        </div>

                        <!-- Timeline -->
                        <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="font-medium">
                                @if($project->start_datetime)
                                    {{ $project->start_datetime->format('M j, Y') }}
                                @else
                                    No start
                                @endif
                                @if($project->end_datetime)
                                    – {{ $project->end_datetime->format('M j, Y') }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div
                     x-data="{
                         editing: false,
                         originalValue: @js($project->description ?? ''),
                         currentValue: @js($project->description ?? ''),
                         startEditing() {
                             this.editing = true;
                             this.currentValue = this.originalValue;
                             $wire.description = this.originalValue;
                             $nextTick(() => $refs.input?.focus());
                         },
                         save() {
                             if (this.currentValue !== this.originalValue) {
                                this.editing = false;

                                $wire.$dispatchTo('workspace.show-items', 'update-project-field', {
                                    projectId: {{ $project->id }},
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
                            <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h.01M5 4h14a2 2 0 012 2v8.5a2 2 0 01-2 2H9.414a2 2 0 00-1.414.586l-2.293 2.293A1 1 0 015 19.086V6a2 2 0 012-2z" />
                                </svg>
                                Description
                            </flux:heading>
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

                <!-- Project Details -->
                <div class="mt-2 rounded-2xl bg-zinc-50 dark:bg-zinc-900/40 px-4 py-4 space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <flux:heading size="xs" class="uppercase tracking-wide text-[0.7rem] text-zinc-500 dark:text-zinc-400">
                            Project Details
                        </flux:heading>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Start Date & Time -->
                        <div
                         x-data="{
                             editing: false,
                             originalValue: @js($project->start_datetime ? $project->start_datetime->format('Y-m-d\TH:i') : ''),
                             currentValue: @js($project->start_datetime ? $project->start_datetime->format('Y-m-d\TH:i') : ''),
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

                                    $wire.$dispatchTo('workspace.show-items', 'update-project-field', {
                                        projectId: {{ $project->id }},
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
                            <flux:heading size="sm" class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 00-2 2z" />
                                </svg>
                                Start Date & Time
                            </flux:heading>
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
                            <p class="text-sm text-zinc-700 dark:text-zinc-300 mt-2 font-medium">
                                {{ $project->start_datetime?->format('M j, Y g:i A') ?? 'Not set' }}
                            </p>
                        </div>
                    </div>

                    <!-- End Date & Time -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($project->end_datetime ? $project->end_datetime->format('Y-m-d\TH:i') : ''),
                             currentValue: @js($project->end_datetime ? $project->end_datetime->format('Y-m-d\TH:i') : ''),
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

                                    $wire.$dispatchTo('workspace.show-items', 'update-project-field', {
                                        projectId: {{ $project->id }},
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
                            <flux:heading size="sm" class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 00-2 2z" />
                                </svg>
                                End Date & Time
                            </flux:heading>
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
                            <p class="text-sm text-zinc-700 dark:text-zinc-300 mt-2 font-medium">
                                {{ $project->end_datetime?->format('M j, Y g:i A') ?? 'Not set' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Progress -->
                <div class="mt-4">
                    <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                        <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h10M4 14h6M4 18h4" />
                        </svg>
                        Progress
                    </flux:heading>
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
                    <div class="mt-6">
                        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h12M9 12h12M9 19h12M4 5h.01M4 12h.01M4 19h.01" />
                            </svg>
                            Associated Tasks
                        </flux:heading>
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
                    <div class="mt-6 border-t border-zinc-200 dark:border-zinc-800 pt-4">
                        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M3 5a2 2 0 012-2h4l2 2h6a2 2 0 012 2v2.586a1 1 0 01-.293.707l-7.414 7.414a2 2 0 01-2.828 0L3.293 9.707A1 1 0 013 9V5z" />
                            </svg>
                            Tags
                        </flux:heading>
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
                    <div class="mt-4">
                        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Reminders
                        </flux:heading>
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
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 mt-4">
                    <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                        <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8zm6 8H6" />
                        </svg>
                        Collaboration
                    </flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                        Collaboration features coming soon...
                    </p>
                </div>

                <!-- Delete Button -->
                <div class="mt-6 mb-4 pt-4 border-t border-red-100/60 dark:border-red-900/40">
                    <div class="flex justify-end" x-data="{}">
                        <flux:button
                            variant="danger"
                            @click="$wire.showDeleteConfirm = true"
                        >
                            Delete Project
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal
        wire:model="showDeleteConfirm"
        class="max-w-md my-10 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl bg-white/95 dark:bg-zinc-900/95"
    >
        <flux:heading size="lg" class="mb-2 text-red-600 dark:text-red-400">Delete Project</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
            Are you sure you want to delete "<strong>{{ $project?->name }}</strong>"? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" @click="$wire.showDeleteConfirm = false">
                Cancel
            </flux:button>
            <flux:button
                variant="danger"
                x-data="{}"
                @click="
                    const projectId = {{ $project?->id ?? 'null' }};
                    if (projectId) {
                        $wire.showDeleteConfirm = false;
                        $wire.isOpen = false;
                        $wire.$dispatchTo('workspace.show-items', 'delete-project', {
                            projectId: projectId,
                        });
                    }
                "
            >
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

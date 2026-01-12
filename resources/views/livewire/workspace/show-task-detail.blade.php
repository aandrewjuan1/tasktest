<?php

use App\Models\Project;
use App\Models\Tag;
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

    public bool $isLoading = false;

    // Edit fields
    public string $title = '';

    public string $description = '';

    public string $status = '';

    public string $priority = '';

    public string $complexity = '';

    public string $duration = '';

    public string $projectId = '';

    public string $eventId = '';

    #[On('view-task-detail')]
    public function openModal(int $id): void
    {
        // Show loading state immediately
        $this->isLoading = true;
        $this->isOpen = true;
        $this->showDeleteConfirm = false;

        // Load task with relationships
        $this->task = Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
            ->findOrFail($id);

        $this->authorize('view', $this->task);

        // Load data before showing content
        $this->loadTaskData();

        // Hide loading state after data is loaded
        $this->isLoading = false;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->task = null;
        $this->showDeleteConfirm = false;
        $this->isLoading = false;
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
        $this->projectId = (string) ($this->task->project_id ?? '');
        $this->eventId = (string) ($this->task->event_id ?? '');
    }

    // All task update mutations are handled by the parent `show-items` component.
    // UI updates are handled through JavaScript events (task-detail-field-updated),
    // so we don't need to listen to item-updated which would cause an extra request.

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

    #[Computed]
    public function availableTags(): Collection
    {
        return Tag::orderBy('name')->get();
    }

    public function createTag(string $name): array
    {
        $name = trim($name);

        if (empty($name)) {
            return ['success' => false, 'message' => 'Tag name cannot be empty'];
        }

        try {
            // Check if tag exists case-insensitively
            $existingTag = Tag::whereRaw('LOWER(name) = LOWER(?)', [$name])->first();

            if ($existingTag) {
                // Tag already exists, return existing tag
                return [
                    'success' => true,
                    'tagId' => $existingTag->id,
                    'tagName' => $existingTag->name,
                    'alreadyExists' => true,
                ];
            }

            // Create new tag
            $tag = Tag::create(['name' => $name]);

            return [
                'success' => true,
                'tagId' => $tag->id,
                'tagName' => $tag->name,
                'alreadyExists' => false,
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to create tag', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);

            return ['success' => false, 'message' => 'Failed to create tag'];
        }
    }

    public function deleteTag(int $tagId): array
    {
        try {
            $tag = Tag::findOrFail($tagId);
            $tag->delete();

            return ['success' => true];
        } catch (\Exception $e) {
            \Log::error('Failed to delete tag', [
                'error' => $e->getMessage(),
                'tagId' => $tagId,
            ]);

            return ['success' => false, 'message' => 'Failed to delete tag'];
        }
    }
}; ?>

<div wire:key="task-detail-modal">
    <flux:modal
        wire:model="isOpen"
        class="w-full max-w-lg sm:max-w-2xl border border-zinc-200 dark:border-zinc-800 shadow-xl bg-white dark:bg-zinc-900"
        variant="flyout"
        closeable="false"
    >
        <div class="space-y-6 px-4 py-4 sm:px-6 sm:py-5" wire:key="task-content-{{ $task?->id ?? 'empty' }}">
            <!-- Loading State -->
            @if($isLoading)
                <div class="flex items-center justify-center py-12">
                    <div class="flex flex-col items-center gap-3">
                        <svg class="animate-spin h-8 w-8 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Loading task details...</p>
                    </div>
                </div>
            @endif

            <!-- Task Content -->
            @if(!$isLoading && $task)
                <!-- Header -->
                <div class="border-b border-zinc-200 dark:border-zinc-800 pb-4">
                    <div class="flex-1"
                         x-data="{
                             editing: false,
                             originalValue: @js($task->title),
                             currentValue: @js($task->title),
                             errorMessage: null,
                             init() {
                                 // Listen for backend updates
                                 window.addEventListener('task-detail-field-updated', (event) => {
                                     const { field, value, taskId } = event.detail || {};
                                     if (field === 'title' && taskId === {{ $task->id }}) {
                                         this.originalValue = value ?? '';
                                         this.currentValue = value ?? '';
                                         $wire.title = value ?? '';
                                         this.errorMessage = null;
                                     }
                                 });
                             },
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.title = this.originalValue;
                                 this.errorMessage = null;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             save() {
                                 // Validate title is not empty or null
                                 const trimmedValue = (this.currentValue || '').trim();
                                 if (!trimmedValue || trimmedValue === '') {
                                     this.errorMessage = 'Title cannot be empty';
                                     return;
                                 }

                                 if (this.currentValue !== this.originalValue) {
                                     this.errorMessage = null;
                                     this.originalValue = this.currentValue;
                                     this.editing = false;

                                     $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                                         taskId: {{ $task->id }},
                                         field: 'title',
                                         value: this.currentValue,
                                     });

                                     // Notify listeners so UI stays in sync
                                     window.dispatchEvent(new CustomEvent('task-detail-field-updated', {
                                         detail: {
                                             field: 'title',
                                             value: this.currentValue,
                                             taskId: {{ $task->id }},
                                         }
                                     }));
                                 } else {
                                     this.editing = false;
                                 }
                             },
                             cancel() {
                                 this.currentValue = this.originalValue;
                                 $wire.title = this.originalValue;
                                 this.errorMessage = null;
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
                                        x-on:input="$wire.title = currentValue"
                                        @keydown.enter.prevent="save()"
                                        @keydown.escape="cancel()"
                                        label="Title"
                                        required
                                    />
                                </div>
                                <div class="flex items-center pt-6">
                                    <button
                                        @click="save()"
                                        class="p-2 text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-lg transition-colors"
                                        type="button"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div x-show="errorMessage" class="mt-1">
                                <p class="text-sm text-red-600 dark:text-red-400" x-text="errorMessage"></p>
                            </div>
                            @error('title')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Tags (simple, near title) -->
                    <div
                        class="mt-3 flex items-center gap-2 flex-wrap"
                        x-data="{
                            selectedTagIds: @js($task->tags->pluck('id')->toArray()),
                            availableTags: @js($this->availableTags->mapWithKeys(fn($tag) => [$tag->id => $tag->name])->toArray()),
                            get displayedTags() {
                                return this.selectedTagIds
                                    .map(id => ({ id: id, name: this.availableTags[id] || '' }))
                                    .filter(tag => tag.name);
                            },
                            init() {
                                // Listen for tag updates
                                window.addEventListener('task-detail-field-updated', (event) => {
                                    const { field, value, taskId } = event.detail || {};
                                    if (field === 'tags' && taskId === {{ $task->id }}) {
                                        this.selectedTagIds = value ?? [];
                                    }
                                });

                                // Listen for new tag creation to update available tags
                                window.addEventListener('tag-created', (event) => {
                                    const { tagId, tagName } = event.detail || {};
                                    if (tagId && tagName) {
                                        this.availableTags[tagId] = tagName;
                                    }
                                });
                            }
                        }"
                    >
                        <template x-if="displayedTags.length > 0">
                            <div class="flex flex-wrap gap-1.5 items-center">
                                <template x-for="tag in displayedTags" :key="tag.id">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                        <span x-text="tag.name"></span>
                                    </span>
                                </template>
                            </div>
                        </template>
                        <span
                            x-show="displayedTags.length === 0"
                            class="text-xs text-zinc-500 dark:text-zinc-400"
                        >Add tags</span>
                        <div wire:key="edit-tags-{{ $task->id }}">
                            <x-workspace.inline-edit-tag-multiselect
                                :item-id="$task->id"
                                item-type="task"
                                :selected-tag-ids="$task->tags->pluck('id')->toArray()"
                                :available-tags="$this->availableTags"
                                :simple-trigger="true"
                                trigger-class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer"
                            />
                        </div>
                    </div>

                    <!-- Key meta pills -->
                    <div class="mt-4 flex flex-wrap gap-2">
                        <!-- Recurring -->
                        <x-workspace.inline-edit-recurrence
                            :item-id="$task->id"
                            :recurring-task="$task->recurringTask"
                        />

                        <!-- Status -->
                        @php
                            $statusColors = [
                                'to_do' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600',
                                'doing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800',
                                'done' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800',
                            ];
                        @endphp
                        <x-inline-edit-dropdown
                            field="status"
                            :item-id="$task->id"
                            :use-parent="true"
                            :value="$task->status?->value ?? 'to_do'"
                            dropdown-class="w-48"
                            trigger-class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full transition-colors cursor-pointer text-sm font-medium"
                            :color-map="$statusColors"
                            default-color-class="bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600"
                        >
                            <x-slot:trigger>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                </svg>
                                <span
                                    x-text="{
                                        to_do: 'To Do',
                                        doing: 'In Progress',
                                        done: 'Done',
                                    }[selectedValue || 'to_do']"
                                ></span>
                            </x-slot:trigger>

                            <x-slot:options>
                                <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                                    Status
                                </div>
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

                        <!-- Priority -->
                        @php
                            $priorityColors = [
                                'low' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600',
                                'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-800',
                                'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300 hover:bg-orange-200 dark:hover:bg-orange-800',
                                'urgent' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800',
                            ];
                        @endphp
                        <x-inline-edit-dropdown
                            field="priority"
                            :item-id="$task->id"
                            :use-parent="true"
                            :value="$task->priority?->value ?? ''"
                            dropdown-class="w-48"
                            trigger-class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full transition-colors cursor-pointer text-sm font-medium"
                            :color-map="$priorityColors"
                            default-color-class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700"
                        >
                            <x-slot:trigger>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <span
                                    x-text="{
                                        low: 'Low',
                                        medium: 'Medium',
                                        high: 'High',
                                        urgent: 'Urgent'
                                    }[selectedValue || ''] || 'No priority'"
                                ></span>
                            </x-slot:trigger>

                            <x-slot:options>
                                <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                                    Priority
                                </div>
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
                        @php
                            $complexityColors = [
                                'simple' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800',
                                'moderate' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-800',
                                'complex' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800',
                            ];
                        @endphp
                        <x-inline-edit-dropdown
                            field="complexity"
                            :item-id="$task->id"
                            :use-parent="true"
                            :value="$task->complexity?->value ?? ''"
                            dropdown-class="w-48"
                            trigger-class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full transition-colors cursor-pointer text-sm font-medium"
                            :color-map="$complexityColors"
                            default-color-class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700"
                        >
                            <x-slot:trigger>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <span
                                    x-text="{
                                        simple: 'Simple',
                                        moderate: 'Moderate',
                                        complex: 'Complex'
                                    }[selectedValue || ''] || 'No complexity'"
                                ></span>
                            </x-slot:trigger>

                            <x-slot:options>
                                <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                                    Complexity
                                </div>
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

                        <!-- Duration -->
                        <x-inline-edit-dropdown
                            field="duration"
                            :item-id="$task->id"
                            :use-parent="true"
                            :value="$task->duration"
                            dropdown-class="w-48 max-h-60 overflow-y-auto"
                            trigger-class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-sm font-medium"
                        >
                            <x-slot:trigger>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span
                                    x-text="selectedValue ? (() => { const mins = parseInt(selectedValue); if (mins >= 60) { const hours = Math.floor(mins / 60); const remainingMins = mins % 60; if (remainingMins === 0) { return hours + (hours === 1 ? ' hour' : ' hours'); } return hours + (hours === 1 ? ' hour' : ' hours') + ' ' + remainingMins + ' min'; } return mins + ' min'; })() : 'No duration'"
                                >@php
                                    if ($task->duration) {
                                        $mins = $task->duration;
                                        if ($mins >= 60) {
                                            $hours = floor($mins / 60);
                                            $remainingMins = $mins % 60;
                                            if ($remainingMins === 0) {
                                                echo $hours . ($hours === 1 ? ' hour' : ' hours');
                                            } else {
                                                echo $hours . ($hours === 1 ? ' hour' : ' hours') . ' ' . $remainingMins . ' min';
                                            }
                                        } else {
                                            echo $mins . ' min';
                                        }
                                    } else {
                                        echo 'No duration';
                                    }
                                @endphp</span>
                            </x-slot:trigger>

                            <x-slot:options>
                                <div class="px-4 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                                    Duration
                                </div>
                                @foreach([15, 30, 45, 60, 90, 120, 180, 240] as $minutes)
                                    @php
                                        $hours = floor($minutes / 60);
                                        $displayText = $minutes < 60
                                            ? $minutes . ' minutes'
                                            : ($hours . ($hours === 1 ? ' hour' : ' hours') . ($minutes === 240 ? '+' : ''));
                                    @endphp
                                    <button
                                        @click="select({{ $minutes }})"
                                        class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                        :class="selectedValue === {{ $minutes }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                    >
                                        {{ $displayText }}
                                    </button>
                                @endforeach
                                <button
                                    @click="select(null)"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    :class="selectedValue === null || selectedValue === '' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                >
                                    Clear
                                </button>
                            </x-slot:options>
                        </x-inline-edit-dropdown>

                        <!-- Start Date -->
                        <x-workspace.inline-edit-date-picker
                            field="startDatetime"
                            :item-id="$task->id"
                            :value="$task->start_datetime?->toIso8601String()"
                            label="Start Date"
                            type="datetime-local"
                            trigger-class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-sm font-medium"
                        />

                        <!-- Due Date -->
                        <x-workspace.inline-edit-date-picker
                            field="endDatetime"
                            :item-id="$task->id"
                            :value="$task->end_datetime?->toIso8601String()"
                            label="Due Date"
                            type="datetime-local"
                            trigger-class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-sm font-medium"
                        />
                    </div>
                </div>

                <!-- Description -->
                <div
                     x-data="{
                         editing: false,
                         originalValue: @js($task->description ?? ''),
                         currentValue: @js($task->description ?? ''),
                         init() {
                             // Listen for backend updates
                             window.addEventListener('task-detail-field-updated', (event) => {
                                 const { field, value, taskId } = event.detail || {};
                                 if (field === 'description' && taskId === {{ $task->id }}) {
                                     this.originalValue = value ?? '';
                                     this.currentValue = value ?? '';
                                     $wire.description = value ?? '';
                                 }
                             });
                         },
                         startEditing() {
                             this.editing = true;
                             this.currentValue = this.originalValue;
                             $wire.description = this.originalValue;
                             $nextTick(() => $refs.input?.focus());
                         },
                         save() {
                             if (this.currentValue !== this.originalValue) {
                                this.originalValue = this.currentValue;
                                this.editing = false;

                                $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                                    taskId: {{ $task->id }},
                                    field: 'description',
                                    value: this.currentValue,
                                });

                                // Notify listeners so UI stays in sync
                                window.dispatchEvent(new CustomEvent('task-detail-field-updated', {
                                    detail: {
                                        field: 'description',
                                        value: this.currentValue,
                                        taskId: {{ $task->id }},
                                    }
                                }));
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
                        <div class="flex items-center">
                            <button
                                @click="save()"
                                class="p-2 text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-lg transition-colors"
                                type="button"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
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

                <!-- Project -->
                @php
                    $projectsData = $this->projects->map(function ($project) {
                        $totalTasks = $project->tasks->count();
                        $completedTasks = $project->tasks->where('status', \App\Enums\TaskStatus::Done)->count();
                        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

                        return [
                            'id' => (string) $project->id,
                            'name' => $project->name,
                            'description' => $project->description,
                            'start_datetime' => $project->start_datetime?->format('M j, Y'),
                            'end_datetime' => $project->end_datetime?->format('M j, Y'),
                            'totalTasks' => $totalTasks,
                            'completedTasks' => $completedTasks,
                            'progress' => $progress,
                        ];
                    })->keyBy('id');

                    $currentProjectData = $task->project ? [
                        'id' => (string) $task->project->id,
                        'name' => $task->project->name,
                        'description' => $task->project->description,
                        'start_datetime' => $task->project->start_datetime?->format('M j, Y'),
                        'end_datetime' => $task->project->end_datetime?->format('M j, Y'),
                        'totalTasks' => $task->project->tasks->count(),
                        'completedTasks' => $task->project->tasks->where('status', \App\Enums\TaskStatus::Done)->count(),
                        'progress' => $task->project->tasks->count() > 0 ? round(($task->project->tasks->where('status', \App\Enums\TaskStatus::Done)->count() / $task->project->tasks->count()) * 100) : 0,
                    ] : null;
                @endphp
                <div
                    x-data="{
                        projects: @js($projectsData),
                        projectNames: @js($this->projects->mapWithKeys(fn($p) => [(string)$p->id => $p->name])->toArray()),
                        selectedProjectId: @js($task->project_id ? (string) $task->project_id : ''),
                        currentProject: @js($currentProjectData),
                        hasProject() {
                            return this.selectedProjectId && this.selectedProjectId !== '' && this.selectedProjectId !== null;
                        },
                        init() {
                            window.addEventListener('task-detail-field-updated', (event) => {
                                const { field, value, taskId } = event.detail || {};
                                if (field === 'projectId' && taskId === {{ $task->id }}) {
                                    this.selectedProjectId = value ?? '';
                                    this.currentProject = value && this.projects[String(value)] ? this.projects[String(value)] : null;
                                }
                            });
                        }
                    }"
                    class="mt-6 border-t border-zinc-200 dark:border-zinc-800 pt-4 overflow-visible"
                >
                    <!-- No Project Selected - Simple Add Button -->
                    <div
                        x-show="!hasProject()"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="flex items-center gap-2"
                    >
                        <x-inline-edit-dropdown
                            field="projectId"
                            :item-id="$task->id"
                            :use-parent="true"
                            :value="$task->project_id ? (string) $task->project_id : ''"
                            dropdown-class="w-56 max-h-60 overflow-y-auto"
                            trigger-class="inline-flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors cursor-pointer"
                        >
                            <x-slot:trigger>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Add to project</span>
                            </x-slot:trigger>

                            <x-slot:options>
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

                    <!-- Project Selected - Full Card -->
                    <div
                        x-show="hasProject()"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-gradient-to-br from-white to-zinc-50 dark:from-zinc-800 dark:to-zinc-900 shadow-lg shadow-zinc-200/50 dark:shadow-zinc-900/50 overflow-visible"
                    >
                        <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30 rounded-t-2xl overflow-visible">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 relative z-10">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    <x-inline-edit-dropdown
                                        field="projectId"
                                        :item-id="$task->id"
                                        :use-parent="true"
                                        :value="$task->project_id ? (string) $task->project_id : ''"
                                        dropdown-class="w-56 max-h-60 overflow-y-auto"
                                        trigger-class="inline-flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors cursor-pointer"
                                        position="top"
                                    >
                                        <x-slot:trigger>
                                            <span x-text="selectedValue && projectNames[String(selectedValue)] ? projectNames[String(selectedValue)] : 'Select a project'"></span>
                                            <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
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
                                <div
                                    x-show="currentProject?.start_datetime || currentProject?.end_datetime"
                                    class="text-xs text-right text-zinc-500 dark:text-zinc-400"
                                >
                                    <div x-show="currentProject?.start_datetime" x-text="'Starts ' + (currentProject?.start_datetime || '')"></div>
                                    <div x-show="currentProject?.end_datetime" x-text="'Ends ' + (currentProject?.end_datetime || '')"></div>
                                </div>
                            </div>
                        </div>

                        <div
                            x-show="currentProject"
                            x-transition:enter="transition ease-out duration-150 delay-75"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="px-5 py-4 space-y-3"
                        >
                            <p
                                x-show="currentProject?.description"
                                class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed"
                                x-text="currentProject?.description"
                            ></p>

                            <div x-show="currentProject?.totalTasks > 0" class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                                <div class="flex items-center justify-between text-sm text-zinc-700 dark:text-zinc-300 mb-2">
                                    <span class="font-medium" x-text="currentProject?.completedTasks + ' of ' + currentProject?.totalTasks + ' tasks completed'"></span>
                                    <span class="font-semibold text-blue-600 dark:text-blue-400" x-text="currentProject?.progress + '%'"></span>
                                </div>
                                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 overflow-hidden">
                                    <div
                                        class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-500 dark:to-blue-400 h-2 rounded-full transition-all duration-500 ease-out"
                                        :style="'width: ' + (currentProject?.progress || 0) + '%'"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reminders -->
                @if($task->reminders->isNotEmpty())
                    <div class="mt-4">
                        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Reminders
                        </flux:heading>
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
                    <div class="mt-4">
                        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Pomodoro Sessions
                        </flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                            {{ $task->pomodoroSessions->count() }} session(s) completed
                        </p>
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
                            Delete Task
                        </flux:button>
                    </div>
                </div>
                @endif
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal
        wire:model="showDeleteConfirm"
        class="max-w-md my-10 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl bg-white dark:bg-zinc-900"
    >
        <flux:heading size="lg" class="mb-2 text-red-600 dark:text-red-400">Delete Task</flux:heading>
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

<?php

use App\Models\Event;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $isOpen = false;

    public ?Event $event = null;

    public bool $showDeleteConfirm = false;

    // Edit fields
    public string $title = '';

    public string $description = '';

    public string $startDatetime = '';

    public string $endDatetime = '';

    public string $status = 'scheduled';

    #[On('view-event-detail')]
    public function openModal(int $id): void
    {
        // Load event first before setting isOpen to prevent DOM flicker
        $this->event = Event::with(['tags', 'reminders'])
            ->findOrFail($id);

        $this->authorize('view', $this->event);

        // Load data before opening modal to ensure stable DOM structure
        $this->loadEventData();

        // Set modal state after data is loaded
        $this->isOpen = true;
        $this->showDeleteConfirm = false;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->event = null;
        $this->showDeleteConfirm = false;
        $this->resetValidation();
    }

    protected function loadEventData(): void
    {
        if (! $this->event) {
            return;
        }

        $this->title = $this->event->title;
        $this->description = $this->event->description ?? '';
        $this->startDatetime = $this->event->start_datetime->format('Y-m-d\TH:i');
        $this->endDatetime = $this->event->end_datetime?->format('Y-m-d\TH:i') ?? '';
        $this->status = $this->event->status?->value ?? 'scheduled';
    }

    public function updateField(string $field, mixed $value): void
    {
        // Intentionally left blank; updates are handled by the parent show-items component.
    }

    public function confirmDelete(): void
    {
        $this->showDeleteConfirm = true;
    }

    public function deleteEvent(): void
    {
        // Intentionally left blank; deletes are handled by the parent show-items component.
    }
}; ?>

<div wire:key="event-detail-modal">
    <flux:modal
        wire:model="isOpen"
        class="min-w-[700px] border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl bg-white/95 dark:bg-zinc-900/95"
        variant="flyout"
        closeable="false"
    >
        <div class="space-y-6 px-6 py-5" wire:key="event-content-{{ $event?->id ?? 'empty' }}">
            @if($event)
                <!-- Header -->
                <div class="border-b border-zinc-200 dark:border-zinc-800 pb-4">
                    <div class="flex-1"
                         x-data="{
                             editing: false,
                             originalValue: @js($event->title),
                             currentValue: @js($event->title),
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.title = this.originalValue;
                                 $nextTick(() => $refs.input?.focus());
                             },
                             save() {
                                 if (this.currentValue !== this.originalValue) {
                                    this.editing = false;

                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
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

                    <!-- Key meta pills -->
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <!-- Status -->
                        <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                            <span class="font-medium">
                                {{ match($event->status?->value ?? 'scheduled') {
                                    'scheduled' => 'Scheduled',
                                    'tentative' => 'Tentative',
                                    'cancelled' => 'Cancelled',
                                    'completed' => 'Completed',
                                    'ongoing' => 'In Progress',
                                    default => 'Scheduled',
                                } }}
                            </span>
                        </div>

                        <!-- Date range -->
                        <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="font-medium">
                                {{ $event->start_datetime->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}
                                @if($event->end_datetime)
                                    – {{ $event->end_datetime->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div
                     x-data="{
                         editing: false,
                         originalValue: @js($event->description ?? ''),
                         currentValue: @js($event->description ?? ''),
                         startEditing() {
                             this.editing = true;
                             this.currentValue = this.originalValue;
                             $wire.description = this.originalValue;
                             $nextTick(() => $refs.input?.focus());
                         },
                         save() {
                             if (this.currentValue !== this.originalValue) {
                                this.editing = false;

                                $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                    eventId: {{ $event->id }},
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

                <!-- Event Details -->
                <div class="mt-2 rounded-2xl bg-zinc-50 dark:bg-zinc-900/40 px-4 py-4 space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 16h14M7 4h.01M7 20h.01" />
                        </svg>
                        <flux:heading size="xs" class="uppercase tracking-wide text-[0.7rem] text-zinc-500 dark:text-zinc-400">
                            Event Details
                        </flux:heading>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                    <!-- Start DateTime -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($event->start_datetime->format('Y-m-d\TH:i')),
                             currentValue: @js($event->start_datetime->format('Y-m-d\TH:i')),
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

                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
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
                                Start
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
                                required
                            />
                            @error('startDatetime')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-700 dark:text-zinc-300 mt-2 font-medium">
                                {{ $event->start_datetime->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}
                            </p>
                        </div>
                    </div>

                    <!-- End DateTime -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($event->end_datetime?->format('Y-m-d\TH:i') ?? ''),
                             currentValue: @js($event->end_datetime?->format('Y-m-d\TH:i') ?? ''),
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

                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
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
                                End
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
                                {{ $event->end_datetime->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}
                            </p>
                        </div>
                    </div>

                    <!-- Status -->
                    <x-inline-edit-dropdown
                        label="Status"
                        field="status"
                        :value="$event->status?->value ?? 'scheduled'"
                    >
                        <x-slot:trigger>
                            <span class="text-sm font-medium inline-flex items-center gap-2">
                                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 4v16m0-12l5-3v16l5-3 4 2V4l-4-2-5 3-5-3z" />
                                </svg>
                                {{ match($event->status?->value ?? 'scheduled') {
                                    'scheduled' => 'Scheduled',
                                    'tentative' => 'Tentative',
                                    'cancelled' => 'Cancelled',
                                    'completed' => 'Completed',
                                    'ongoing' => 'In Progress',
                                    default => 'Scheduled'
                                } }}
                            </span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="
                                    select('scheduled');
                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
                                        field: 'status',
                                        value: 'scheduled',
                                    });
                                "
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'scheduled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Scheduled
                            </button>
                            <button
                                @click="
                                    select('ongoing');
                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
                                        field: 'status',
                                        value: 'ongoing',
                                    });
                                "
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'ongoing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                In Progress
                            </button>
                            <button
                                @click="
                                    select('tentative');
                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
                                        field: 'status',
                                        value: 'tentative',
                                    });
                                "
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'tentative' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Tentative
                            </button>
                            <button
                                @click="
                                    select('cancelled');
                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
                                        field: 'status',
                                        value: 'cancelled',
                                    });
                                "
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'cancelled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Cancelled
                            </button>
                            <button
                                @click="
                                    select('completed');
                                    $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                                        eventId: {{ $event->id }},
                                        field: 'status',
                                        value: 'completed',
                                    });
                                "
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'completed' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Completed
                            </button>
                        </x-slot:options>

                        @error('status')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </x-inline-edit-dropdown>
                </div>

                <!-- Tags -->
                @if($event->tags->isNotEmpty())
                    <div class="mt-6 border-t border-zinc-200 dark:border-zinc-800 pt-4">
                        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M3 5a2 2 0 012-2h4l2 2h6a2 2 0 012 2v2.586a1 1 0 01-.293.707l-7.414 7.414a2 2 0 01-2.828 0L3.293 9.707A1 1 0 013 9V5z" />
                            </svg>
                            Tags
                        </flux:heading>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($event->tags as $tag)
                                <span class="inline-flex items-center px-3 py-1 text-sm rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ $tag->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Reminders -->
                @if($event->reminders->isNotEmpty())
                    <div class="mt-4">
                        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Reminders
                        </flux:heading>
                        <div class="mt-2 space-y-2">
                            @foreach($event->reminders as $reminder)
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
                            Delete Event
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
        <flux:heading size="lg" class="mb-2 text-red-600 dark:text-red-400">Delete Event</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
            Are you sure you want to delete "<strong>{{ $event?->title }}</strong>"? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" @click="$wire.showDeleteConfirm = false">
                Cancel
            </flux:button>
            <flux:button
                variant="danger"
                x-data="{}"
                @click="
                    const eventId = {{ $event?->id ?? 'null' }};
                    if (eventId) {
                        $wire.showDeleteConfirm = false;
                        $wire.isOpen = false;
                        $wire.$dispatchTo('workspace.show-items', 'delete-event', {
                            eventId: eventId,
                        });
                    }
                "
            >
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

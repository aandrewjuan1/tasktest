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
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout" closeable="false">
        <div class="space-y-6" wire:key="event-content-{{ $event?->id ?? 'empty' }}">
            @if($event)
                <!-- Header -->
                <div>
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

                <!-- Event Details Grid -->
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
                            <flux:heading size="sm">Start</flux:heading>
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
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
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
                            <flux:heading size="sm">End</flux:heading>
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
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
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
                            <span class="text-sm font-medium">
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
                    <div>
                        <flux:heading size="sm">Tags</flux:heading>
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
                    <div>
                        <flux:heading size="sm">Reminders</flux:heading>
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
                        Delete Event
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteConfirm" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Delete Event</flux:heading>
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

<?php

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    public bool $allDay = false;

    public string $location = '';

    public string $color = '#3b82f6';

    public string $status = 'scheduled';

    #[On('view-event-detail')]
    public function openModal(int $id): void
    {
        $this->event = Event::with(['tags', 'reminders'])
            ->findOrFail($id);

        $this->authorize('view', $this->event);

        $this->isOpen = true;
        $this->showDeleteConfirm = false;
        $this->loadEventData();
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
        $this->allDay = $this->event->all_day;
        $this->location = $this->event->location ?? '';
        $this->color = $this->event->color ?? '#3b82f6';
        $this->status = $this->event->status?->value ?? 'scheduled';
    }

    public function updateField(string $field, mixed $value): void
    {
        if (! $this->event) {
            return;
        }

        $this->authorize('update', $this->event);

        $validationRules = match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startDatetime' => ['required', 'date'],
            'endDatetime' => ['nullable', 'date'],
            'allDay' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'status' => ['nullable', 'string', 'in:scheduled,cancelled,completed,tentative'],
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
                    case 'startDatetime':
                        $startDatetime = Carbon::parse($value);
                        $updateData['start_datetime'] = $startDatetime;
                        // Auto-calculate end_datetime if not provided
                        if (empty($this->endDatetime)) {
                            $updateData['end_datetime'] = $startDatetime->copy()->addHour();
                        }
                        break;
                    case 'endDatetime':
                        $updateData['end_datetime'] = $value ? Carbon::parse($value) : null;
                        break;
                    case 'allDay':
                        $updateData['all_day'] = (bool) $value;
                        break;
                    case 'location':
                        $updateData['location'] = $value ?: null;
                        break;
                    case 'color':
                        $updateData['color'] = $value;
                        break;
                    case 'status':
                        $updateData['status'] = $value ?: 'scheduled';
                        break;
                }

                $this->event->update($updateData);
                $this->event->refresh();
            });

            $this->loadEventData();
            $this->dispatch('event-updated');
            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update event field', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'event_id' => $this->event->id,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update event. Please try again.', type: 'error');
        }
    }

    public function confirmDelete(): void
    {
        $this->showDeleteConfirm = true;
    }

    public function deleteEvent(): void
    {
        $this->authorize('delete', $this->event);

        try {
            DB::transaction(function () {
                $this->event->delete();
            });

            $this->closeModal();
            $this->dispatch('event-deleted');
            session()->flash('message', 'Event deleted successfully!');
        } catch (\Exception $e) {
            \Log::error('Failed to delete event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event_id' => $this->event->id,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete event. Please try again.', type: 'error');
        }
    }
}; ?>

<div>
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout" closeable="false">
        @if($event)
            <div class="space-y-6">
                <!-- Header -->
                <div>
                    <div class="flex-1"
                         x-data="{
                             editing: false,
                             originalValue: @js($event->title),
                             currentValue: @js($event->title),
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
                         originalValue: @js($event->description ?? ''),
                         currentValue: @js($event->description ?? ''),
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
                                     $wire.updateField('startDatetime', this.currentValue).then(() => {
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
                                @if($event->all_day)
                                    {{ $event->start_datetime->setTimezone('Asia/Manila')->format('M j, Y') }} (All day)
                                @else
                                    {{ $event->start_datetime->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}
                                @endif
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
                                     $wire.updateField('endDatetime', this.currentValue).then(() => {
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
                                @if($event->all_day)
                                    {{ $event->end_datetime->setTimezone('Asia/Manila')->format('M j, Y') }} (All day)
                                @else
                                    {{ $event->end_datetime->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- All Day -->
                    <div
                         x-data="{
                             editing: false,
                             originalValue: @js($event->all_day),
                             currentValue: @js($event->all_day),
                             mouseLeaveTimer: null,
                             startEditing() {
                                 this.editing = true;
                                 this.currentValue = this.originalValue;
                                 $wire.allDay = this.originalValue;
                             },
                             cancelEditing() {
                                 this.editing = false;
                                 this.currentValue = this.originalValue;
                                 $wire.allDay = this.originalValue;
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
                                     $wire.updateField('allDay', this.currentValue).then(() => {
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
                            <flux:heading size="sm">All Day</flux:heading>
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
                            <flux:checkbox
                                x-model="currentValue"
                                x-on:change="$wire.allDay = currentValue"
                                wire:model.live="allDay"
                            />
                            @error('allDay')
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2" x-text="originalValue ? 'Yes' : 'No'"></p>
                        </div>
                    </div>

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
                                {{ match($event->status?->value ?? 'scheduled') {
                                    'scheduled' => 'Scheduled',
                                    'tentative' => 'Tentative',
                                    'cancelled' => 'Cancelled',
                                    'completed' => 'Completed',
                                    default => 'Scheduled'
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
                                @click="selectStatus('scheduled')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'scheduled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Scheduled
                            </button>
                            <button
                                @click="selectStatus('tentative')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'tentative' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Tentative
                            </button>
                            <button
                                @click="selectStatus('cancelled')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'cancelled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Cancelled
                            </button>
                            <button
                                @click="selectStatus('completed')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ ($event->status?->value ?? 'scheduled') === 'completed' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}"
                            >
                                Completed
                            </button>
                        </div>
                        @error('status')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Location -->
                <div
                     x-data="{
                         editing: false,
                         originalValue: @js($event->location ?? ''),
                         currentValue: @js($event->location ?? ''),
                         mouseLeaveTimer: null,
                         startEditing() {
                             this.editing = true;
                             this.currentValue = this.originalValue;
                             $wire.location = this.originalValue;
                             $nextTick(() => $refs.input?.focus());
                         },
                         cancelEditing() {
                             this.editing = false;
                             this.currentValue = this.originalValue;
                             $wire.location = this.originalValue;
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
                                 $wire.updateField('location', this.currentValue).then(() => {
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
                        <flux:heading size="sm">Location</flux:heading>
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
                            x-on:input="$wire.location = currentValue"
                            wire:model.live="location"
                            @keydown.enter="saveIfChanged()"
                            @keydown.escape="cancelEditing()"
                        />
                        @error('location')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div x-show="!editing">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2" x-text="originalValue || 'No location specified'"></p>
                    </div>
                </div>

                <!-- Color -->
                <div
                     x-data="{
                         editing: false,
                         originalValue: @js($event->color ?? '#3b82f6'),
                         currentValue: @js($event->color ?? '#3b82f6'),
                         mouseLeaveTimer: null,
                         startEditing() {
                             this.editing = true;
                             this.currentValue = this.originalValue;
                             $wire.color = this.originalValue;
                         },
                         cancelEditing() {
                             this.editing = false;
                             this.currentValue = this.originalValue;
                             $wire.color = this.originalValue;
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
                                 $wire.updateField('color', this.currentValue).then(() => {
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
                        <flux:heading size="sm">Color</flux:heading>
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
                            x-model="currentValue"
                            x-on:change="$wire.color = currentValue"
                            wire:model.live="color"
                            type="color"
                        />
                        @error('color')
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div x-show="!editing">
                        <div class="flex items-center gap-2 mt-2">
                            <span class="w-6 h-6 rounded" :style="`background-color: ${originalValue}`"></span>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400" x-text="originalValue"></span>
                        </div>
                    </div>
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
                <div class="flex justify-end mt-6 mb-4">
                    <flux:button
                        variant="danger"
                        @click="$wire.showDeleteConfirm = true"
                    >
                        Delete Event
                    </flux:button>
                </div>
            </div>
        @endif
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
            <flux:button variant="danger" wire:click="deleteEvent">
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

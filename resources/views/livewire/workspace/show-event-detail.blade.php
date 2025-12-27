<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\Event;
use App\Models\Tag;

new class extends Component {
    public bool $isOpen = false;
    public ?Event $event = null;
    public bool $editMode = false;

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
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if ($this->event) {
            $this->isOpen = true;
            $this->editMode = false;
            $this->loadEventData();
        }
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->event = null;
        $this->editMode = false;
        $this->resetValidation();
    }

    public function toggleEditMode(): void
    {
        $this->editMode = !$this->editMode;
        if ($this->editMode) {
            $this->loadEventData();
        }
        $this->resetValidation();
    }

    protected function loadEventData(): void
    {
        if (!$this->event) {
            return;
        }

        $this->title = $this->event->title;
        $this->description = $this->event->description ?? '';
        $this->startDatetime = $this->event->start_datetime->format('Y-m-d\TH:i');
        $this->endDatetime = $this->event->end_datetime->format('Y-m-d\TH:i');
        $this->allDay = $this->event->all_day;
        $this->location = $this->event->location ?? '';
        $this->color = $this->event->color ?? '#3b82f6';
        $this->status = $this->event->status->value;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'startDatetime' => 'required|date',
            'endDatetime' => 'required|date|after:startDatetime',
            'allDay' => 'boolean',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'status' => 'required|string|in:scheduled,cancelled,completed,tentative',
        ]);

        $this->event->update([
            'title' => $this->title,
            'description' => $this->description ?: null,
            'start_datetime' => $this->startDatetime,
            'end_datetime' => $this->endDatetime,
            'all_day' => $this->allDay,
            'location' => $this->location ?: null,
            'color' => $this->color,
            'status' => $this->status,
        ]);

        $this->event->refresh();
        $this->editMode = false;
        $this->dispatch('event-updated');
        session()->flash('message', 'Event updated successfully!');
    }

    public function deleteEvent(): void
    {
        $this->event->delete();
        $this->closeModal();
        $this->dispatch('event-deleted');
        session()->flash('message', 'Event deleted successfully!');
    }
}; ?>

<div>
    <flux:modal wire:model="isOpen" class="min-w-[700px]" variant="flyout">
        @if($event)
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        @if($editMode)
                            <flux:input wire:model="title" label="Title" required />
                        @else
                            <flux:heading size="lg">{{ $event->title }}</flux:heading>
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
                                wire:click="deleteEvent"
                                wire:confirm="Are you sure you want to delete this event?"
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
                            {{ $event->description ?: 'No description provided.' }}
                        </p>
                    @endif
                </div>

                <!-- Event Details Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Start DateTime -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="startDatetime" label="Start Date & Time" type="datetime-local" required />
                        @else
                            <flux:heading size="sm">Start</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                @if($event->all_day)
                                    {{ $event->start_datetime->format('M j, Y') }} (All day)
                                @else
                                    {{ $event->start_datetime->format('M j, Y g:i A') }}
                                @endif
                            </p>
                        @endif
                    </div>

                    <!-- End DateTime -->
                    <div>
                        @if($editMode)
                            <flux:input wire:model="endDatetime" label="End Date & Time" type="datetime-local" required />
                        @else
                            <flux:heading size="sm">End</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                @if($event->all_day)
                                    {{ $event->end_datetime->format('M j, Y') }} (All day)
                                @else
                                    {{ $event->end_datetime->format('M j, Y g:i A') }}
                                @endif
                            </p>
                        @endif
                    </div>

                    <!-- All Day -->
                    <div>
                        @if($editMode)
                            <flux:checkbox wire:model="allDay" label="All Day Event" />
                        @else
                            <flux:heading size="sm">All Day</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                                {{ $event->all_day ? 'Yes' : 'No' }}
                            </p>
                        @endif
                    </div>

                    <!-- Status -->
                    <div>
                        @if($editMode)
                            <flux:select wire:model="status" label="Status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="tentative">Tentative</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </flux:select>
                        @else
                            <flux:heading size="sm">Status</flux:heading>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded mt-2 {{ match($event->status->value) {
                                'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                'completed' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                'tentative' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                            } }}">
                                {{ ucfirst($event->status->value) }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Location -->
                <div>
                    @if($editMode)
                        <flux:input wire:model="location" label="Location" />
                    @else
                        <flux:heading size="sm">Location</flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                            {{ $event->location ?: 'No location specified' }}
                        </p>
                    @endif
                </div>

                <!-- Color -->
                <div>
                    @if($editMode)
                        <flux:input wire:model="color" label="Color" type="color" />
                    @else
                        <flux:heading size="sm">Color</flux:heading>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="w-6 h-6 rounded" style="background-color: {{ $event->color }}"></span>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $event->color }}</span>
                        </div>
                    @endif
                </div>

                <!-- Tags -->
                @if(!$editMode && $event->tags->isNotEmpty())
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
                @if(!$editMode && $event->reminders->isNotEmpty())
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

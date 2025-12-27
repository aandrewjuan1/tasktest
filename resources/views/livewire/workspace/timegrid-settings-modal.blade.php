<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\TimegridSetting;

new class extends Component {
    public bool $showModal = false;
    public int $startHour = 6;
    public int $endHour = 22;
    public int $hourHeight = 60;
    public bool $showWeekends = true;
    public int $defaultEventDuration = 30;
    public int $slotIncrement = 15;

    public function mount(): void
    {
        $this->loadSettings();
    }

    #[On('open-timegrid-settings')]
    public function openModal(): void
    {
        $this->loadSettings();
        $this->showModal = true;
    }

    public function loadSettings(): void
    {
        $settings = auth()->user()->timegridSetting;

        if ($settings) {
            $this->startHour = $settings->start_hour;
            $this->endHour = $settings->end_hour;
            $this->hourHeight = $settings->hour_height;
            $this->showWeekends = $settings->show_weekends;
            $this->defaultEventDuration = $settings->default_event_duration;
            $this->slotIncrement = $settings->slot_increment;
        }
    }

    public function save(): void
    {
        $this->validate([
            'startHour' => 'required|integer|min:0|max:23',
            'endHour' => 'required|integer|min:0|max:23|gt:startHour',
            'hourHeight' => 'required|integer|min:40|max:120',
            'defaultEventDuration' => 'required|integer|min:15|max:480',
            'slotIncrement' => 'required|integer|in:15,30,60',
        ]);

        $user = auth()->user();

        $settings = $user->timegridSetting()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'start_hour' => $this->startHour,
                'end_hour' => $this->endHour,
                'hour_height' => $this->hourHeight,
                'show_weekends' => $this->showWeekends,
                'default_event_duration' => $this->defaultEventDuration,
                'slot_increment' => $this->slotIncrement,
            ]
        );

        $this->showModal = false;
        $this->dispatch('timegrid-settings-updated');
        session()->flash('message', 'Timegrid settings saved successfully!');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }
}; ?>

<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="$wire.closeModal()"></div>

            <!-- Modal -->
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="relative bg-white dark:bg-zinc-800 rounded-lg shadow-xl max-w-2xl w-full p-6" @click.stop>
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                            Timegrid Settings
                        </h2>
                        <button wire:click="closeModal" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <form wire:submit.prevent="save" class="space-y-6">
                        <!-- Time Range -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:select wire:model="startHour" label="Start Hour">
                                    @for($h = 0; $h <= 23; $h++)
                                        <option value="{{ $h }}">{{ Carbon\Carbon::createFromTime($h, 0)->format('g:00 A') }}</option>
                                    @endfor
                                </flux:select>
                            </div>
                            <div>
                                <flux:select wire:model="endHour" label="End Hour">
                                    @for($h = 0; $h <= 23; $h++)
                                        <option value="{{ $h }}">{{ Carbon\Carbon::createFromTime($h, 0)->format('g:00 A') }}</option>
                                    @endfor
                                </flux:select>
                            </div>
                        </div>

                        <!-- Hour Height Slider -->
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                                Hour Height: {{ $hourHeight }}px
                            </label>
                            <input
                                type="range"
                                wire:model.live="hourHeight"
                                min="40"
                                max="120"
                                step="10"
                                class="w-full h-2 bg-zinc-200 rounded-lg appearance-none cursor-pointer dark:bg-zinc-700"
                            />
                            <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                <span>Compact (40px)</span>
                                <span>Standard (60px)</span>
                                <span>Spacious (120px)</span>
                            </div>
                        </div>

                        <!-- Slot Increment -->
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                                Time Slot Increment
                            </label>
                            <div class="flex gap-4">
                                <label class="flex items-center">
                                    <input type="radio" wire:model="slotIncrement" value="15" class="mr-2">
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">15 minutes</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" wire:model="slotIncrement" value="30" class="mr-2">
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">30 minutes</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" wire:model="slotIncrement" value="60" class="mr-2">
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">1 hour</span>
                                </label>
                            </div>
                        </div>

                        <!-- Default Event Duration -->
                        <div>
                            <flux:input
                                wire:model="defaultEventDuration"
                                type="number"
                                min="15"
                                max="480"
                                step="15"
                                label="Default Event Duration (minutes)"
                            />
                        </div>

                        <!-- Show Weekends -->
                        <div>
                            <flux:checkbox
                                wire:model="showWeekends"
                                label="Show Weekends"
                            />
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:button type="button" variant="ghost" wire:click="closeModal">
                                Cancel
                            </flux:button>
                            <flux:button type="submit" variant="primary">
                                Save Settings
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;

new
#[Title('Workspace')]
class extends Component {
    #[On('task-created')]
    #[On('event-created')]
    #[On('project-created')]
    public function refreshItems(): void
    {
        // This will trigger a re-render of child components
    }
}; ?>

<div class="h-screen flex flex-col overflow-hidden">
    <!-- Flash Messages -->
    @if (session('message'))
        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-4" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Main Layout: 75/25 Split -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-4 gap-6 overflow-hidden">
        <!-- Left Column: Tasks, Events, Projects (75%) -->
        <div class="lg:col-span-3 overflow-y-auto">
            <livewire:workspace.show-items :key="'show-items-'.now()" />
        </div>

        <!-- Right Column: Calendar (25%) -->
        <div class="lg:col-span-1 overflow-y-auto">
            <livewire:workspace.calendar-view :key="'calendar-'.now()" />
        </div>
    </div>

    <!-- Create Modal -->
    <livewire:workspace.create-item-modal />

    <!-- Detail View Modals -->
    <livewire:workspace.show-task-detail />
    <livewire:workspace.show-event-detail />
    <livewire:workspace.show-project-detail />

    <!-- Calendar Event Popover -->
    <livewire:workspace.calendar-event-popover />
</div>

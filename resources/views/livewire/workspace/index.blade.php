<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;

new
#[Title('Workspace')]
class extends Component {
}; ?>

<div class="min-h-screen flex flex-col">
    <!-- Toast Notifications -->
    <x-toast />

    <!-- Offline Indicator -->
    <div wire:offline class="fixed top-0 left-0 right-0 bg-yellow-500 text-white px-4 py-2 text-center z-50" role="alert" aria-live="assertive">
        <span class="block sm:inline">You're currently offline. Changes will sync when connection is restored.</span>
    </div>

    <!-- Flash Messages -->
    @if (session('message'))
        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-4" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Main Layout: 75/25 Split -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-4 gap-6 p-6">
        <!-- Left Column: Tasks, Events, Projects (75%) -->
        <div class="lg:col-span-3">
            <livewire:workspace.show-items wire:key="workspace-show-items" />
        </div>

        <!-- Right Column: Calendar (25%) -->
        <div class="lg:col-span-1">
            <livewire:workspace.calendar-view wire:key="workspace-calendar-view" />
        </div>
    </div>

    <!-- Detail View Modal -->
    <livewire:workspace.show-task-detail wire:key="workspace-task-detail" />
</div>

<?php

use App\Models\Project;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    #[Computed]
    public function availableProjects(): Collection
    {
        return Project::accessibleBy(auth()->user())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableTags(): Collection
    {
        $user = auth()->user();

        return Tag::whereHas('tasks', function ($query) use ($user) {
            $query->accessibleBy($user);
        })
            ->orWhereHas('events', function ($query) use ($user) {
                $query->accessibleBy($user);
            })
            ->orWhereHas('projects', function ($query) use ($user) {
                $query->accessibleBy($user);
            })
            ->orderBy('name')
            ->get();
    }
}; ?>

<div x-data="{
    activeTab: 'task',
    isOpen: false,
    formData: {
        task: {
            title: '',
            status: 'to_do',
            priority: 'medium',
            complexity: 'moderate',
            duration: 60,
            startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
            endDatetime: null,
            projectId: null,
            tagIds: []
        },
        event: {
            title: '',
            status: 'scheduled',
            startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
            endDatetime: null,
            tagIds: []
        },
        project: {
            name: '',
            startDate: '{{ Carbon::today()->toDateString() }}',
            endDate: null,
            tagIds: []
        }
    },
    openModal() {
        this.activeTab = 'task';
        this.isOpen = true;
        document.body.style.overflow = 'hidden';
    },
    closeModal() {
        this.isOpen = false;
        document.body.style.overflow = '';
    },
    switchTab(tab) {
        this.activeTab = tab;
    },
    get inputValue() {
        if (this.activeTab === 'task') {
            return this.formData.task.title;
        } else if (this.activeTab === 'event') {
            return this.formData.event.title;
        } else {
            return this.formData.project.name;
        }
    },
    set inputValue(value) {
        if (this.activeTab === 'task') {
            this.formData.task.title = value;
        } else if (this.activeTab === 'event') {
            this.formData.event.title = value;
        } else {
            this.formData.project.name = value;
        }
    },
    resetFormData() {
        this.formData = {
            task: {
                title: '',
                status: 'to_do',
                priority: 'medium',
                complexity: 'moderate',
                duration: 60,
                startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
                endDatetime: null,
                projectId: null,
                tagIds: []
            },
            event: {
                title: '',
                status: 'scheduled',
                startDatetime: '{{ Carbon::now()->format('Y-m-d\TH:i') }}',
                endDatetime: null,
                location: null,
                color: null,
                tagIds: []
            },
            project: {
                name: '',
                startDate: '{{ Carbon::today()->toDateString() }}',
                endDate: null,
                tagIds: []
            }
        };
    },
    submitTask() {
        this.closeModal();

        const payload = { ...this.formData.task };

        $wire.$parent.$call('createTask', payload);
    },
    submitEvent() {
        this.closeModal();

        const payload = { ...this.formData.event };

        $wire.$parent.$call('createEvent', payload);
    },
    submitProject() {
        this.closeModal();

        const payload = { ...this.formData.project };

        $wire.$parent.$call('createProject', payload);
    },
    toggleTag(tagId, type) {
        const tagIds = this.formData[type].tagIds;
        const index = tagIds.indexOf(tagId);
        if (index > -1) {
            tagIds.splice(index, 1);
        } else {
            tagIds.push(tagId);
        }
    },
    isTagSelected(tagId, type) {
        return this.formData[type].tagIds.includes(tagId);
    }
}"
     @open-create-modal.window="openModal()"
     @close-create-modal.window="closeModal()"
     @item-created.window="resetFormData()">

    <!-- Form Container -->
    <div
        x-show="isOpen"
        x-transition:enter="transition-transform ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition-transform ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        @click.away="closeModal()"
        class="fixed bottom-0 left-1/2 -translate-x-1/2 w-3/4 z-50 px-4 bg-white dark:bg-zinc-900 rounded-t-3xl shadow-2xl border-2 border-zinc-300 dark:border-zinc-600"
        x-cloak
    >
        <div class="py-4 space-y-4" @click.stop>
            <!-- Top Section: Title Input Field -->
            <div class="space-y-3">
                <!-- Tabs -->
                <div class="flex gap-2 items-center justify-between">
                    <div class="flex gap-2">
                    <button
                        @click.stop="switchTab('task')"
                        :class="activeTab === 'task' ? 'bg-blue-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                    >
                        Task
                    </button>
                    <button
                        @click.stop="switchTab('event')"
                        :class="activeTab === 'event' ? 'bg-blue-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                    >
                        Event
                    </button>
                    <button
                        @click.stop="switchTab('project')"
                        :class="activeTab === 'project' ? 'bg-blue-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                    >
                        Project
                    </button>
                    </div>
                    <!-- Collapse Button -->
                    <button
                        @click.stop="closeModal()"
                        class="p-1.5 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors"
                        aria-label="Collapse"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Input Field and Submit Button -->
                <div class="flex gap-2 items-center">
                    <input
                        type="text"
                        x-model="inputValue"
                        required
                        @keydown.enter.prevent="!inputValue.trim() || (activeTab === 'task' ? submitTask() : (activeTab === 'event' ? submitEvent() : submitProject()))"
                        @click.stop
                        :placeholder="activeTab === 'task' ? 'Enter task title...' : (activeTab === 'event' ? 'Enter event title...' : 'Enter project name...')"
                        class="flex-1 px-6 py-4 rounded-full border-2 border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    />
                    <button
                        @click.stop="!inputValue.trim() || (activeTab === 'task' ? submitTask() : (activeTab === 'event' ? submitEvent() : submitProject()))"
                        :disabled="!inputValue.trim()"
                        :class="!inputValue.trim() ? 'opacity-50 cursor-not-allowed' : ''"
                        class="px-6 py-4 rounded-full bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700 text-white font-medium transition-colors flex items-center justify-center gap-2 whitespace-nowrap disabled:bg-zinc-400 dark:disabled:bg-zinc-600 disabled:hover:bg-zinc-400 dark:disabled:hover:bg-zinc-600"
                    >
                        <span x-text="activeTab === 'task' ? 'Create Task' : (activeTab === 'event' ? 'Create Event' : 'Create Project')"></span>
                    </button>
                </div>
            </div>

            <!-- Bottom Section: Property Buttons -->
            <div class="flex flex-wrap gap-2" @click.stop>
                <template x-if="activeTab === 'task'">
                    <div class="contents">
                    <!-- Task Priority -->
                    <x-inline-create-dropdown dropdown-class="w-48">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span class="text-sm font-medium">Priority</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.task.priority || 'Medium'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.task.priority = 'low')"
                                :class="formData.task.priority === 'low' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Low
                            </button>
                            <button
                                @click="select(() => formData.task.priority = 'medium')"
                                :class="formData.task.priority === 'medium' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Medium
                            </button>
                            <button
                                @click="select(() => formData.task.priority = 'high')"
                                :class="formData.task.priority === 'high' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                High
                            </button>
                            <button
                                @click="select(() => formData.task.priority = 'urgent')"
                                :class="formData.task.priority === 'urgent' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Urgent
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Complexity -->
                    <x-inline-create-dropdown dropdown-class="w-48">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span class="text-sm font-medium">Complexity</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.task.complexity || 'Moderate'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.task.complexity = 'simple')"
                                :class="formData.task.complexity === 'simple' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Simple
                            </button>
                            <button
                                @click="select(() => formData.task.complexity = 'moderate')"
                                :class="formData.task.complexity === 'moderate' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Moderate
                            </button>
                            <button
                                @click="select(() => formData.task.complexity = 'complex')"
                                :class="formData.task.complexity === 'complex' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Complex
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Duration -->
                    <x-inline-create-dropdown dropdown-class="w-48 max-h-60 overflow-y-auto">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium">Duration</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.duration ? formData.task.duration + ' min' : 'Not set'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            @foreach([15, 30, 45, 60, 90, 120, 180, 240, 300] as $minutes)
                                <button
                                    @click="select(() => formData.task.duration = {{ $minutes }})"
                                    :class="formData.task.duration === {{ $minutes }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                >
                                    {{ $minutes }} minutes
                                </button>
                            @endforeach
                            <button
                                @click="select(() => formData.task.duration = null)"
                                :class="formData.task.duration === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Clear
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Start Date & Time -->
                    <x-workspace.inline-date-picker
                        label="Start Date &amp; Time"
                        model="formData.task.startDatetime"
                        type="datetime-local"
                    />

                    <!-- Task End Date & Time -->
                    <x-workspace.inline-date-picker
                        label="End Date &amp; Time"
                        model="formData.task.endDatetime"
                        type="datetime-local"
                    />

                    <!-- Task Project -->
                    <x-inline-create-dropdown dropdown-class="w-48 max-h-60 overflow-y-auto">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <span class="text-sm font-medium">Project</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="formData.task.projectId ? 'Selected' : 'None'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.task.projectId = null)"
                                :class="formData.task.projectId === null ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                None
                            </button>
                            @foreach($this->availableProjects as $project)
                            <button
                                wire:key="project-{{ $project->id }}"
                                @click="select(() => formData.task.projectId = {{ $project->id }})"
                                :class="formData.task.projectId === {{ $project->id }} ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                {{ $project->name }}
                            </button>
                            @endforeach
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Task Tags -->
                    <x-workspace.inline-tag-multiselect type="task" />
                    </div>
                </template>
                <template x-if="activeTab === 'event'">
                    <div class="contents">
                    <!-- Event Status -->
                    <x-inline-create-dropdown dropdown-class="w-48">
                        <x-slot:trigger>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                            <span class="text-sm font-medium">Status</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize" x-text="formData.event.status || 'Scheduled'"></span>
                        </x-slot:trigger>

                        <x-slot:options>
                            <button
                                @click="select(() => formData.event.status = 'scheduled')"
                                :class="formData.event.status === 'scheduled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Scheduled
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'ongoing')"
                                :class="formData.event.status === 'ongoing' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                In Progress
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'tentative')"
                                :class="formData.event.status === 'tentative' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Tentative
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'completed')"
                                :class="formData.event.status === 'completed' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Completed
                            </button>
                            <button
                                @click="select(() => formData.event.status = 'cancelled')"
                                :class="formData.event.status === 'cancelled' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Cancelled
                            </button>
                        </x-slot:options>
                    </x-inline-create-dropdown>

                    <!-- Event Start Date & Time -->
                    <x-workspace.inline-date-picker
                        label="Start Date &amp; Time"
                        model="formData.event.startDatetime"
                        type="datetime-local"
                    />

                    <!-- Event End Date & Time -->
                    <x-workspace.inline-date-picker
                        label="End Date &amp; Time"
                        model="formData.event.endDatetime"
                        type="datetime-local"
                    />

                    <!-- Event Tags -->
                    <x-workspace.inline-tag-multiselect type="event" />
                    </div>
                </template>
                <template x-if="activeTab === 'project'">
                    <div class="contents">
                        <!-- Project Start Date -->
                    <x-workspace.inline-date-picker
                        label="Start Date"
                        model="formData.project.startDate"
                        type="date"
                    />

                        <!-- Project End Date -->
                    <x-workspace.inline-date-picker
                        label="End Date"
                        model="formData.project.endDate"
                        type="date"
                    />

                        <!-- Project Tags -->
                    <x-workspace.inline-tag-multiselect type="project" />
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

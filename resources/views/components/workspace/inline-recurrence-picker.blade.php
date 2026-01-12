@props([
    'model',
    'type' => 'task', // 'task' or 'event'
])

<x-inline-create-dropdown dropdown-class="w-96 p-4">
    <x-slot:trigger>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span class="text-sm font-medium">Repeat</span>
        <span
            class="text-xs text-zinc-500 dark:text-zinc-400"
            x-text="{{ $model }}.enabled && {{ $model }}.type ? ({{ $model }}.type === 'daily' ? 'Daily' : ({{ $model }}.type === 'weekly' ? 'Weekly' : ({{ $model }}.type === 'monthly' ? 'Monthly' : ({{ $model }}.type === 'yearly' ? 'Yearly' : 'Repeating')))) + ({{ $model }}.interval > 1 ? ' (Every ' + {{ $model }}.interval + ')' : '') : 'Not repeating'"
        ></span>
    </x-slot:trigger>

    <x-slot:options>
        <div
            class="space-y-4 px-3 pb-3 pt-1"
            x-data="{
                toggleEnabled() {
                    const wasEnabled = {{ $model }}.enabled;
                    {{ $model }}.enabled = !{{ $model }}.enabled;
                    if (!{{ $model }}.enabled) {
                        {{ $model }}.type = null;
                        {{ $model }}.interval = 1;
                        {{ $model }}.daysOfWeek = [];
                    } else if (!wasEnabled) {
                        if (!{{ $model }}.type) {
                            {{ $model }}.type = 'daily';
                        }
                        if (!{{ $model }}.interval || {{ $model }}.interval < 1) {
                            {{ $model }}.interval = 1;
                        }
                    }
                },
                setType(newType) {
                    {{ $model }}.type = newType;
                    if (newType !== 'weekly') {
                        {{ $model }}.daysOfWeek = [];
                    }
                },
                toggleDayOfWeek(day) {
                    const days = {{ $model }}.daysOfWeek || [];
                    const index = days.indexOf(day);
                    if (index > -1) {
                        days.splice(index, 1);
                    } else {
                        days.push(day);
                        days.sort();
                    }
                    {{ $model }}.daysOfWeek = days;
                },
                isDaySelected(day) {
                    return ({{ $model }}.daysOfWeek || []).includes(day);
                },
                init() {
                    // Initialize recurrence object if it doesn't exist
                    if (!{{ $model }}) {
                        {{ $model }} = {};
                    }
                    if ({{ $model }}.enabled === undefined) {
                        {{ $model }}.enabled = false;
                    }
                    if ({{ $model }}.type === undefined) {
                        {{ $model }}.type = null;
                    }
                    if ({{ $model }}.interval === undefined) {
                        {{ $model }}.interval = 1;
                    }
                    if ({{ $model }}.daysOfWeek === undefined) {
                        {{ $model }}.daysOfWeek = [];
                    }
                    if ({{ $model }}.enabled) {
                        if (!{{ $model }}.type) {
                            {{ $model }}.type = 'daily';
                        }
                        if (!{{ $model }}.interval || {{ $model }}.interval < 1) {
                            {{ $model }}.interval = 1;
                        }
                    }
                }
            }"
            x-init="init()"
        >
            <!-- Enable/Disable Toggle -->
            <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Repeat {{ ucfirst($type) }}</span>
                <button
                    type="button"
                    @click="toggleEnabled()"
                    :class="{{ $model }}.enabled ? 'bg-blue-500' : 'bg-zinc-300 dark:bg-zinc-600'"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <span
                        :class="{{ $model }}.enabled ? 'translate-x-6' : 'translate-x-1'"
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    ></span>
                </button>
            </div>

            <template x-if="{{ $model }}.enabled">
                <div class="space-y-4 pt-2">
                    <!-- Recurrence Type -->
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-2">Repeat Type</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="setType('daily')"
                                :class="{{ $model }}.type === 'daily' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Daily
                            </button>
                            <button
                                type="button"
                                @click="setType('weekly')"
                                :class="{{ $model }}.type === 'weekly' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Weekly
                            </button>
                            <button
                                type="button"
                                @click="setType('monthly')"
                                :class="{{ $model }}.type === 'monthly' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Monthly
                            </button>
                            <button
                                type="button"
                                @click="setType('yearly')"
                                :class="{{ $model }}.type === 'yearly' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Yearly
                            </button>
                        </div>
                    </div>

                    <template x-if="{{ $model }}.type">
                        <div class="space-y-4">
                            <!-- Interval -->
                            <div>
                                <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Repeat Every</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        min="1"
                                        x-model.number="{{ $model }}.interval"
                                        class="w-20 h-8 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2 text-xs text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400" x-text="{{ $model }}.type + ({{ $model }}.interval > 1 ? 's' : '')"></span>
                                </div>
                            </div>

                            <!-- Days of Week (for Weekly) -->
                            <template x-if="{{ $model }}.type === 'weekly'">
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-2">Days of Week</label>
                                    <div class="grid grid-cols-7 gap-1">
                                        <template x-for="(day, index) in ['S', 'M', 'T', 'W', 'T', 'F', 'S']" :key="index">
                                            <button
                                                type="button"
                                                @click="toggleDayOfWeek(index)"
                                                :class="isDaySelected(index) ? 'bg-blue-500 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                                                class="h-8 w-8 rounded-lg text-xs font-medium transition-colors"
                                                x-text="day"
                                            ></button>
                                        </template>
                                    </div>
                                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span x-text="({{ $model }}.daysOfWeek || []).length === 0 ? 'Select at least one day' : (({{ $model }}.daysOfWeek || []).length + ' day(s) selected')"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </x-slot:options>
</x-inline-create-dropdown>

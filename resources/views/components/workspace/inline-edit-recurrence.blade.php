@props([
    'itemId',
    'recurringTask' => null,
    'dropdownClass' => 'w-96 p-4',
    'triggerClass' => 'inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/40 transition-colors cursor-pointer text-sm font-medium',
    'position' => 'bottom',
    'disabled' => false,
])

@php
    // Prepare initial recurrence data
    $initialData = [
        'enabled' => $recurringTask !== null,
        'type' => $recurringTask?->recurrence_type?->value ?? null,
        'interval' => $recurringTask?->interval ?? 1,
        'daysOfWeek' => $recurringTask?->days_of_week ? array_map('intval', explode(',', $recurringTask->days_of_week)) : [],
        'startDatetime' => $recurringTask?->start_datetime?->toIso8601String() ?? null,
        'endDatetime' => $recurringTask?->end_datetime?->toIso8601String() ?? null,
    ];
@endphp

<div
    {{ $attributes->class('relative') }}
    x-data="{
        open: false,
        mouseLeaveTimer: null,
        recurrence: @js($initialData),
        disabled: @js($disabled),
        init() {
            // Initialize defaults for enabled recurrence without type
            if (this.recurrence.enabled && !this.recurrence.type) {
                this.recurrence.type = 'daily';
            }
            if (!this.recurrence.interval || this.recurrence.interval < 1) {
                this.recurrence.interval = 1;
            }

            // Listen for backend updates
            window.addEventListener('task-detail-field-updated', (event) => {
                const { field, value, taskId } = event.detail || {};
                if (field === 'recurrence' && taskId === {{ $itemId }}) {
                    if (value === null || !value.enabled) {
                        this.recurrence = {
                            enabled: false,
                            type: null,
                            interval: 1,
                            daysOfWeek: [],
                            startDatetime: null,
                            endDatetime: null,
                        };
                    } else {
                        this.recurrence = {
                            enabled: value.enabled ?? false,
                            type: value.type ?? null,
                            interval: value.interval ?? 1,
                            daysOfWeek: value.daysOfWeek ?? [],
                            startDatetime: value.startDatetime ?? null,
                            endDatetime: value.endDatetime ?? null,
                        };
                        // Ensure default type if enabled but no type
                        if (this.recurrence.enabled && !this.recurrence.type) {
                            this.recurrence.type = 'daily';
                        }
                    }
                }
            });
        },
        getDisplayText() {
            if (!this.recurrence.enabled || !this.recurrence.type) {
                return 'Add recurrence';
            }
            const typeLabels = {
                daily: 'Daily',
                weekly: 'Weekly',
                monthly: 'Monthly',
                yearly: 'Yearly',
            };
            let text = typeLabels[this.recurrence.type] || 'Recurring';
            if (this.recurrence.interval > 1) {
                text += ' (Every ' + this.recurrence.interval + ')';
            }
            if (this.recurrence.type === 'weekly' && this.recurrence.daysOfWeek && this.recurrence.daysOfWeek.length > 0) {
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                const selectedDays = this.recurrence.daysOfWeek.map(d => dayNames[d]).join(', ');
                text += ' (' + selectedDays + ')';
            }
            return text;
        },
        toggleDropdown() {
            if (this.disabled) {
                return;
            }
            this.open = !this.open;
        },
        closeDropdown() {
            this.open = false;
        },
        handleMouseEnter() {
            if (this.mouseLeaveTimer) {
                clearTimeout(this.mouseLeaveTimer);
                this.mouseLeaveTimer = null;
            }
        },
        handleMouseLeave() {
            this.mouseLeaveTimer = setTimeout(() => {
                this.closeDropdown();
            }, 300);
        },
        intervalDebounceTimer: null,
        saveRecurrence() {
            if (this.disabled) {
                return;
            }
            const value = this.recurrence.enabled ? {
                enabled: this.recurrence.enabled,
                type: this.recurrence.type,
                interval: this.recurrence.interval,
                daysOfWeek: this.recurrence.daysOfWeek,
                startDatetime: this.recurrence.startDatetime,
                endDatetime: this.recurrence.endDatetime,
            } : null;

            $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                taskId: {{ $itemId }},
                field: 'recurrence',
                value: value,
            });

            // Notify listeners so UI stays in sync
            window.dispatchEvent(new CustomEvent('task-detail-field-updated', {
                detail: {
                    field: 'recurrence',
                    value: value,
                    taskId: {{ $itemId }},
                }
            }));
        },
        toggleEnabled() {
            const wasEnabled = this.recurrence.enabled;
            this.recurrence.enabled = !this.recurrence.enabled;
            if (!this.recurrence.enabled) {
                this.recurrence.type = null;
                this.recurrence.interval = 1;
                this.recurrence.daysOfWeek = [];
            } else if (!wasEnabled) {
                // When enabling, set default to daily if no type is set
                if (!this.recurrence.type) {
                    this.recurrence.type = 'daily';
                }
                if (!this.recurrence.interval || this.recurrence.interval < 1) {
                    this.recurrence.interval = 1;
                }
            }
            this.saveRecurrence();
        },
        setType(newType) {
            this.recurrence.type = newType;
            if (newType !== 'weekly') {
                this.recurrence.daysOfWeek = [];
                // Auto-save for daily, monthly, yearly
                this.saveRecurrence();
            }
            // For weekly, don't save yet - wait for day selection
        },
        toggleDayOfWeek(day) {
            const days = this.recurrence.daysOfWeek || [];
            const index = days.indexOf(day);
            if (index > -1) {
                days.splice(index, 1);
            } else {
                days.push(day);
                days.sort();
            }
            this.recurrence.daysOfWeek = days;
            // Only save if at least one day is selected
            if (days.length > 0) {
                this.saveRecurrence();
            }
        },
        isDaySelected(day) {
            return (this.recurrence.daysOfWeek || []).includes(day);
        },
        updateInterval() {
            // Debounce interval updates to avoid too many saves while typing
            if (this.intervalDebounceTimer) {
                clearTimeout(this.intervalDebounceTimer);
            }
            this.intervalDebounceTimer = setTimeout(() => {
                this.saveRecurrence();
            }, 500);
        }
    }"
    @mouseenter="handleMouseEnter()"
    @mouseleave="handleMouseLeave()"
    @click.outside="closeDropdown()"
>
    <button
        type="button"
        @click.stop="toggleDropdown()"
        x-bind:class="disabled ? '{{ $triggerClass }} opacity-50 cursor-not-allowed' : '{{ $triggerClass }}'"
        x-bind:disabled="disabled"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span x-text="getDisplayText()"></span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute {{ $position === 'top' ? 'bottom-full mb-1' : 'top-full mt-1' }} z-50 {{ $dropdownClass }} bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700"
    >
        <div class="space-y-4 px-3 pb-3 pt-1">
            <!-- Enable/Disable Toggle -->
            <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Repeat Task</span>
                <button
                    type="button"
                    @click="toggleEnabled()"
                    :class="recurrence.enabled ? 'bg-blue-500' : 'bg-zinc-300 dark:bg-zinc-600'"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <span
                        :class="recurrence.enabled ? 'translate-x-6' : 'translate-x-1'"
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    ></span>
                </button>
            </div>

            <template x-if="recurrence.enabled">
                <div class="space-y-4 pt-2">
                    <!-- Recurrence Type -->
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-2">Repeat Type</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="setType('daily')"
                                :class="recurrence.type === 'daily' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Daily
                            </button>
                            <button
                                type="button"
                                @click="setType('weekly')"
                                :class="recurrence.type === 'weekly' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Weekly
                            </button>
                            <button
                                type="button"
                                @click="setType('monthly')"
                                :class="recurrence.type === 'monthly' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Monthly
                            </button>
                            <button
                                type="button"
                                @click="setType('yearly')"
                                :class="recurrence.type === 'yearly' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border-blue-500' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700'"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition-colors"
                            >
                                Yearly
                            </button>
                        </div>
                    </div>

                    <template x-if="recurrence.type">
                        <div class="space-y-4">
                            <!-- Interval -->
                            <div>
                                <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Repeat Every</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        min="1"
                                        x-model.number="recurrence.interval"
                                        @input="updateInterval()"
                                        class="w-20 h-8 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2 text-xs text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400" x-text="recurrence.type + (recurrence.interval > 1 ? 's' : '')"></span>
                                </div>
                            </div>

                            <!-- Days of Week (for Weekly) -->
                            <template x-if="recurrence.type === 'weekly'">
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
                                        <span x-text="(recurrence.daysOfWeek || []).length === 0 ? 'Select at least one day' : ((recurrence.daysOfWeek || []).length + ' day(s) selected')"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

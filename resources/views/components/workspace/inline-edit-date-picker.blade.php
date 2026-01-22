@props([
    'field',
    'itemId',
    'value' => null,
    'label',
    'type' => 'datetime-local',
    'dropdownClass' => 'w-80 p-4',
    'triggerClass' => 'inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-sm font-medium',
    'position' => 'bottom',
    'disabled' => false,
    'itemType' => 'task', // 'task' or 'event'
])

@php
    // Format the initial value for display
    $displayValue = 'Not set';
    if ($value) {
        try {
            $date = \Carbon\Carbon::parse($value);
            $displayValue = $date->format('M j, Y g:i A');
        } catch (\Exception $e) {
            $displayValue = 'Not set';
        }
    }
    // Format value for JavaScript (ISO 8601 string)
    $jsValue = $value ? \Carbon\Carbon::parse($value)->toIso8601String() : null;
@endphp

<x-inline-edit-dropdown
    field="{{ $field }}"
    :item-id="$itemId"
    item-type="{{ $itemType }}"
    :use-parent="true"
    :value="$jsValue"
    dropdown-class="{{ $dropdownClass }}"
    trigger-class="{{ $triggerClass }}"
    position="{{ $position }}"
    :disabled="$disabled"
>
    <x-slot:trigger>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">{{ $label }}</span>
        <span
            class="text-xs text-zinc-500 dark:text-zinc-400"
            x-data="{
                initialValue: @js($jsValue),
                getValue() {
                    // If parent has optimistic UI state, use that
                    if (this.$parent && typeof this.$parent['{{ $field }}'] !== 'undefined') {
                        return this.$parent['{{ $field }}'];
                    }
                    // Fallback to dropdown's selectedValue if available
                    if (typeof selectedValue !== 'undefined' && selectedValue !== null && selectedValue !== '') {
                        return selectedValue;
                    }
                    return this.initialValue;
                },
                getDisplayText() {
                    const value = this.getValue();
                    if (!value || value === null || value === '') {
                        return 'Not set';
                    }
                    try {
                        // Parse ISO string directly to avoid timezone conversion issues
                        // Format: YYYY-MM-DDTHH:mm:ss or YYYY-MM-DDTHH:mm:ssZ
                        let dateStr = value;
                        if (dateStr.includes('T')) {
                            const [datePart, timePart] = dateStr.split('T');
                            const [year, month, day] = datePart.split('-').map(Number);

                            // Parse time part (handle Z or timezone)
                            let timeStr = timePart.replace(/Z$/, '').split(/[+-]/)[0];
                            const [hours, minutes] = timeStr.split(':').map(Number);

                            // Create date using UTC to preserve the original date
                            const date = new Date(Date.UTC(year, month - 1, day, hours || 0, minutes || 0, 0));

                            if (isNaN(date.getTime())) {
                                return 'Not set';
                            }

                            // Format using UTC methods to preserve date
                            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            const monthName = months[date.getUTCMonth()];
                            const dayNum = date.getUTCDate();
                            const yearNum = date.getUTCFullYear();

                            // Format time in 12-hour format
                            let hour12 = date.getUTCHours();
                            const minute = date.getUTCMinutes();
                            const ampm = hour12 >= 12 ? 'PM' : 'AM';
                            hour12 = hour12 % 12;
                            hour12 = hour12 ? hour12 : 12; // 0 should be 12

                            return `${monthName} ${dayNum}, ${yearNum} ${hour12}:${String(minute).padStart(2, '0')} ${ampm}`;
                        } else {
                            // Fallback to regular date parsing if format is different
                            const date = new Date(value);
                            if (isNaN(date.getTime())) {
                                return 'Not set';
                            }
                            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                        }
                    } catch(e) {
                        return 'Not set';
                    }
                }
            }"
            x-text="getDisplayText()"
        >{{ $displayValue }}</span>
    </x-slot:trigger>

    <x-slot:options>
        <div
            class="space-y-3 px-3 pb-3 pt-1"
            x-data="{
                type: '{{ $type }}',
                month: null,
                year: null,
                selectedDate: null,
                hour: '',
                minute: '',
                meridiem: 'AM',
                days: [],
                currentValue: @js($jsValue),
                updateModelTimer: null,

                getValue() {
                    // If parent has optimistic UI state, use that
                    if (this.$parent && typeof this.$parent['{{ $field }}'] !== 'undefined') {
                        return this.$parent['{{ $field }}'];
                    }
                    return this.currentValue;
                },

                syncFromParent() {
                    const value = this.getValue();
                    // Always sync, even if value is the same, to ensure selectedDate is updated
                    this.currentValue = value;
                    if (value) {
                        this.parseInitial(value);
                    } else {
                        this.selectedDate = null;
                    }
                    // Always ensure month/year are set for calendar display
                    const baseDate = this.selectedDate ?? new Date();
                    this.month = baseDate.getMonth();
                    this.year = baseDate.getFullYear();
                    if (this.type === 'datetime-local' && value && (!this.hour || !this.minute)) {
                        const now = this.selectedDate ?? new Date();
                        this.setTimeFromDate(now);
                    }
                    this.buildDays();
                },

                init() {
                    // Watch parent state for changes and sync
                    const fieldName = '{{ $field }}';
                    if (this.$parent && typeof this.$parent[fieldName] !== 'undefined') {
                        this.$watch(`$parent.${fieldName}`, () => {
                            this.syncFromParent();
                        });
                        // Sync initially
                        this.syncFromParent();
                    } else {
                        // Initialize month/year from currentValue or use current date
                        const initialValue = this.currentValue;
                        if (initialValue) {
                            this.parseInitial(initialValue);
                        }

                        // Always set month/year for calendar display
                        const baseDate = this.selectedDate ?? new Date();
                        this.month = baseDate.getMonth();
                        this.year = baseDate.getFullYear();

                        if (this.type === 'datetime-local' && initialValue && (!this.hour || !this.minute)) {
                            const now = this.selectedDate ?? new Date();
                            this.setTimeFromDate(now);
                        }

                        // Build days for calendar
                        this.buildDays();
                    }
                },

                parseInitial(value) {
                    if (!value) {
                        return;
                    }

                    // Parse ISO string directly to avoid timezone conversion issues
                    let parsed;
                    try {
                        if (typeof value === 'string' && value.includes('T')) {
                            // Parse ISO 8601 format: YYYY-MM-DDTHH:mm:ss or YYYY-MM-DDTHH:mm:ssZ
                            const [datePart, timePart] = value.split('T');
                            const dateComponents = datePart.split('-').map(Number);

                            if (dateComponents.length !== 3) {
                                // Fallback to standard parsing
                                parsed = new Date(value);
                            } else {
                                const [year, month, day] = dateComponents;

                                // Parse time part (handle Z or timezone offset)
                                let timeStr = timePart.replace(/Z$/, '').split(/[+-]/)[0];
                                const timeComponents = timeStr.split(':').map(Number);
                                const hours = timeComponents[0] || 0;
                                const minutes = timeComponents[1] || 0;

                                // Create date using local timezone to preserve the date components
                                // This ensures Jan 13 in database shows as Jan 13, not Jan 18
                                parsed = new Date(year, month - 1, day, hours, minutes, 0);
                            }
                        } else {
                            // Fallback for non-ISO format
                            parsed = new Date(value);
                        }
                    } catch (e) {
                        // If parsing fails, try standard Date constructor
                        parsed = new Date(value);
                    }

                    if (isNaN(parsed.getTime())) {
                        return;
                    }

                    this.selectedDate = parsed;

                    if (this.type === 'datetime-local') {
                        this.setTimeFromDate(parsed);
                    }
                },

                setTimeFromDate(date) {
                    let hours = date.getHours();
                    const minutes = date.getMinutes();

                    this.meridiem = hours >= 12 ? 'PM' : 'AM';

                    let hour12 = hours % 12;
                    if (hour12 === 0) {
                        hour12 = 12;
                    }

                    this.hour = String(hour12).padStart(2, '0');
                    this.minute = String(minutes).padStart(2, '0');
                },

                buildDays() {
                    const firstDayOfMonth = new Date(this.year, this.month, 1).getDay();
                    const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();

                    this.days = [];

                    for (let i = 0; i < firstDayOfMonth; i++) {
                        this.days.push({ label: '', date: null });
                    }

                    for (let day = 1; day <= daysInMonth; day++) {
                        this.days.push({ label: day, date: day });
                    }
                },

                changeMonth(offset) {
                    const newMonth = this.month + offset;
                    const date = new Date(this.year, newMonth, 1);
                    this.month = date.getMonth();
                    this.year = date.getFullYear();
                    this.buildDays();
                },

                selectDay(day) {
                    if (!day) {
                        return;
                    }

                    this.selectedDate = new Date(this.year, this.month, day);
                    // Close dropdown immediately
                    if (this.$parent && typeof this.$parent.open !== 'undefined') {
                        this.$parent.open = false;
                    }
                    this.updateModel();
                },

                normalizeHour() {
                    let h = parseInt(this.hour || '0', 10);
                    if (isNaN(h) || h < 1) {
                        h = 1;
                    }
                    if (h > 12) {
                        h = 12;
                    }
                    this.hour = String(h).padStart(2, '0');
                },

                normalizeMinute() {
                    let m = parseInt(this.minute || '0', 10);
                    if (isNaN(m) || m < 0) {
                        m = 0;
                    }
                    if (m > 59) {
                        m = 59;
                    }
                    this.minute = String(m).padStart(2, '0');
                },

                updateTime() {
                    this.normalizeHour();
                    this.normalizeMinute();
                    // Throttle updateModel to prevent rapid updates
                    if (this.updateModelTimer) {
                        clearTimeout(this.updateModelTimer);
                    }
                    this.updateModelTimer = setTimeout(() => {
                        this.updateModel();
                    }, 300);
                },

                selectToday() {
                    const now = new Date();
                    this.selectedDate = now;

                    if (this.type === 'datetime-local') {
                        this.setTimeFromDate(now);
                    }

                    this.month = now.getMonth();
                    this.year = now.getFullYear();
                    this.buildDays();
                    // updateModel will close the dropdown
                    this.updateModel();
                },

                async clearSelection() {
                    const originalValue = this.currentValue;
                    this.selectedDate = null;
                    this.hour = '';
                    this.minute = '';
                    this.meridiem = 'AM';
                    this.currentValue = null;

                    // Check if parent Alpine component has updateField method (optimistic UI pattern)
                    const parentHasOptimistic = this.$parent && typeof this.$parent.updateField === 'function';
                    const fieldName = '{{ $field }}';

                    // Update parent state optimistically if available
                    if (parentHasOptimistic && typeof this.$parent[fieldName] !== 'undefined') {
                        this.$parent[fieldName] = null;
                    }

                    // Close parent dropdown immediately (frontend state) - set open to false directly
                    if (this.$parent && typeof this.$parent.open !== 'undefined') {
                        this.$parent.open = false;
                    } else if (this.$parent && typeof this.$parent.closeDropdown === 'function') {
                        this.$parent.closeDropdown();
                    }

                    const itemType = '{{ $itemType }}';
                    const itemId = {{ $itemId }};

                    try {
                        if (parentHasOptimistic) {
                            // Use parent's optimistic update method
                            await this.$parent.updateField(fieldName, null);
                        } else {
                            // Fallback to direct wire call
                            if (itemType === 'event') {
                                await $wire.$parent.call('updateEventField', itemId, fieldName, null);
                            } else {
                                await $wire.$parent.call('updateTaskField', itemId, fieldName, null);
                            }
                        }
                    } catch (error) {
                        // Rollback on error
                        this.currentValue = originalValue;
                        if (parentHasOptimistic && typeof this.$parent[fieldName] !== 'undefined') {
                            this.$parent[fieldName] = originalValue;
                        }
                        console.error('Failed to clear field:', error);
                    }
                },

                async updateModel() {
                    if (!this.selectedDate) {
                        return;
                    }

                    const date = new Date(this.selectedDate);

                    if (this.type === 'datetime-local') {
                        let hours = parseInt(this.hour || '12', 10);
                        const minutes = parseInt(this.minute || '0', 10);

                        if (isNaN(hours) || hours < 1 || hours > 12) {
                            hours = 12;
                        }

                        if (this.meridiem === 'PM' && hours < 12) {
                            hours += 12;
                        }

                        if (this.meridiem === 'AM' && hours === 12) {
                            hours = 0;
                        }

                        date.setHours(hours, isNaN(minutes) ? 0 : minutes, 0, 0);
                    } else {
                        date.setHours(0, 0, 0, 0);
                    }

                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');

                    let value = `${year}-${month}-${day}`;

                    if (this.type === 'datetime-local') {
                        const hours24 = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        value += `T${hours24}:${minutes}`;
                    }

                    const originalValue = this.currentValue;
                    this.currentValue = value;

                    // Check if parent Alpine component has updateField method (optimistic UI pattern)
                    const parentHasOptimistic = this.$parent && typeof this.$parent.updateField === 'function';
                    const fieldName = '{{ $field }}';

                    // Update parent state optimistically if available
                    if (parentHasOptimistic && typeof this.$parent[fieldName] !== 'undefined') {
                        this.$parent[fieldName] = value;
                    }

                    // Close parent dropdown immediately (frontend state) - set open to false directly
                    if (this.$parent && typeof this.$parent.open !== 'undefined') {
                        this.$parent.open = false;
                    } else if (this.$parent && typeof this.$parent.closeDropdown === 'function') {
                        this.$parent.closeDropdown();
                    }

                    const itemType = '{{ $itemType }}';
                    const itemId = {{ $itemId }};

                    try {
                        if (parentHasOptimistic) {
                            // Use parent's optimistic update method
                            await this.$parent.updateField(fieldName, value);
                        } else {
                            // Fallback to direct wire call
                            if (itemType === 'event') {
                                await $wire.$parent.call('updateEventField', itemId, fieldName, value);
                            } else {
                                await $wire.$parent.call('updateTaskField', itemId, fieldName, value);
                            }
                        }
                    } catch (error) {
                        // Rollback on error
                        this.currentValue = originalValue;
                        if (parentHasOptimistic && typeof this.$parent[fieldName] !== 'undefined') {
                            this.$parent[fieldName] = originalValue;
                        }
                        console.error('Failed to update field:', error);
                    }
                },

                isSelected(day) {
                    if (!day) {
                        return false;
                    }

                    // Get current value from parent Alpine state
                    const currentValue = this.getValue();
                    if (!currentValue) {
                        return false;
                    }

                    // Parse the current value to get the date
                    let dateToCheck;
                    try {
                        if (typeof currentValue === 'string' && currentValue.includes('T')) {
                            const [datePart, timePart] = currentValue.split('T');
                            const [year, month, dayFromValue] = datePart.split('-').map(Number);
                            dateToCheck = new Date(year, month - 1, dayFromValue);
                        } else {
                            dateToCheck = new Date(currentValue);
                        }
                    } catch (e) {
                        return false;
                    }

                    if (isNaN(dateToCheck.getTime())) {
                        return false;
                    }

                    return (
                        dateToCheck.getFullYear() === this.year &&
                        dateToCheck.getMonth() === this.month &&
                        dateToCheck.getDate() === day
                    );
                },

                isToday(day) {
                    if (!day) {
                        return false;
                    }

                    const today = new Date();
                    return (
                        today.getFullYear() === this.year &&
                        today.getMonth() === this.month &&
                        today.getDate() === day
                    );
                },

                get monthLabel() {
                    const date = new Date(this.year, this.month, 1);
                    return date.toLocaleDateString(undefined, {
                        month: 'long',
                        year: 'numeric',
                    });
                },
            }"
        >
            <div class="pt-1 pb-2">
                <!-- Header -->
                <div class="mb-3 flex items-center justify-between px-1">
                    <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        @click.prevent="changeMonth(-1)"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <div
                        class="text-sm font-semibold text-zinc-900 dark:text-zinc-50"
                        x-text="monthLabel"
                    ></div>

                    <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        @click.prevent="changeMonth(1)"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Weekday headings -->
                <div class="mb-2 grid grid-cols-7 gap-1 text-center text-[11px] font-medium text-zinc-400 dark:text-zinc-500">
                    <span>Su</span>
                    <span>Mo</span>
                    <span>Tu</span>
                    <span>We</span>
                    <span>Th</span>
                    <span>Fr</span>
                    <span>Sa</span>
                </div>

                <!-- Days grid -->
                <div class="grid grid-cols-7 gap-1">
                    <template x-for="(day, index) in days" :key="index">
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-sm transition-colors"
                            :class="day.date
                                ? (isSelected(day.date)
                                    ? 'bg-pink-500 text-white shadow-sm'
                                    : (isToday(day.date)
                                        ? 'text-pink-600 dark:text-pink-400'
                                        : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'))
                                : 'pointer-events-none bg-transparent'"
                            @click.prevent="selectDay(day.date)"
                            x-text="day.label"
                        ></button>
                    </template>
                </div>

                <!-- Time controls -->
                <div class="mt-3 border-t border-zinc-100 pt-3 text-xs dark:border-zinc-800">
                    <div
                        class="flex items-center justify-between"
                        x-show="type === 'datetime-local'"
                    >
                        <span class="font-medium text-zinc-500 dark:text-zinc-400">
                            Time
                        </span>

                        <div class="flex items-center gap-1.5">
                            <input
                                type="number"
                                min="1"
                                max="12"
                                x-model="hour"
                                @change="updateTime()"
                                class="h-8 w-12 rounded-lg border border-zinc-200 bg-zinc-50 px-1 text-center text-xs text-zinc-900 shadow-sm outline-none ring-0 focus:border-pink-500 focus:bg-white focus:ring-1 focus:ring-pink-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:focus:border-pink-400 dark:focus:ring-pink-400"
                            />
                            <span class="pb-1 text-sm text-zinc-400 dark:text-zinc-500">:</span>
                            <input
                                type="number"
                                min="0"
                                max="59"
                                x-model="minute"
                                @change="updateTime()"
                                class="h-8 w-12 rounded-lg border border-zinc-200 bg-zinc-50 px-1 text-center text-xs text-zinc-900 shadow-sm outline-none ring-0 focus:border-pink-500 focus:bg-white focus:ring-1 focus:ring-pink-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:focus:border-pink-400 dark:focus:ring-pink-400"
                            />

                            <select
                                x-model="meridiem"
                                @change="updateTime()"
                                class="h-8 rounded-lg border border-zinc-200 bg-zinc-50 px-2 text-xs font-medium text-zinc-900 shadow-sm outline-none ring-0 focus:border-pink-500 focus:bg-white focus:ring-1 focus:ring-pink-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:focus:border-pink-400 dark:focus:ring-pink-400"
                            >
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium text-pink-600 hover:bg-pink-50 dark:text-pink-400 dark:hover:bg-pink-900/20"
                            @click.throttle.prevent="selectToday()"
                        >
                            Today
                        </button>

                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                            x-show="selectedDate"
                            @click.throttle.prevent="clearSelection()"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:options>
</x-inline-edit-dropdown>

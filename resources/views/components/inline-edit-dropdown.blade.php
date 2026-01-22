@props([
    'field',
    'value' => null,
    'useParent' => false,
    'itemId' => null,
    'itemType' => 'task', // 'task' or 'event'
    'instanceDate' => null, // For recurring task/event instances
    'dropdownClass' => 'w-48',
    'triggerClass' => 'flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors',
    'position' => 'bottom', // 'top' or 'bottom'
    'colorMap' => null, // Optional: array mapping values to color classes
    'defaultColorClass' => null, // Optional: default color class when value is empty/null
    'disabled' => false,
])

<div
    {{ $attributes->class('relative') }}
    x-data="{
        open: false,
        mouseLeaveTimer: null,
        selectedValue: @js($value ? $value : null),
        colorMap: @js($colorMap),
        defaultColorClass: @js($defaultColorClass),
        disabled: @js($disabled),
        getValue() {
            // If parent has optimistic UI state, use that
            if (this.$parent && typeof this.$parent['{{ $field }}'] !== 'undefined') {
                return this.$parent['{{ $field }}'];
            }
            return this.selectedValue;
        },
        getTriggerClass() {
            const baseClass = '{{ $triggerClass }}';
            let classes = baseClass;
            if (this.disabled) {
                classes += ' opacity-50 cursor-not-allowed';
            }
            if (!this.colorMap) {
                return classes;
            }
            const value = this.getValue() || '';
            const colorClass = value && this.colorMap[value] ? this.colorMap[value] : (this.defaultColorClass || '');
            return classes + (colorClass ? ' ' + colorClass : '');
        },
        init() {
            // Watch parent state for changes and sync selectedValue
            const fieldName = '{{ $field }}';
            if (this.$parent && typeof this.$parent[fieldName] !== 'undefined') {
                this.$watch(`$parent.${fieldName}`, (newValue) => {
                    this.selectedValue = newValue ?? null;
                });
                // Sync initially
                this.selectedValue = this.$parent[fieldName] ?? null;
            }

            @if($useParent && $itemId)
                const itemType = '{{ $itemType }}';
                const itemId = {{ $itemId }};

                // Listen for backend updates (both task and event)
                window.addEventListener('task-detail-field-updated', (event) => {
                    const { field, value, taskId } = event.detail || {};
                    if (itemType === 'task' && field === '{{ $field }}' && taskId === itemId) {
                        this.selectedValue = value ?? null;
                        // Also update parent if it has optimistic UI
                        if (this.$parent && typeof this.$parent['{{ $field }}'] !== 'undefined') {
                            this.$parent['{{ $field }}'] = value ?? null;
                        }
                    }
                });

                window.addEventListener('event-detail-field-updated', (event) => {
                    const { field, value, eventId } = event.detail || {};
                    if (itemType === 'event' && field === '{{ $field }}' && eventId === itemId) {
                        this.selectedValue = value ?? null;
                        // Also update parent if it has optimistic UI
                        if (this.$parent && typeof this.$parent['{{ $field }}'] !== 'undefined') {
                            this.$parent['{{ $field }}'] = value ?? null;
                        }
                    }
                });
            @endif
        },
        formatDuration(minutes) {
            if (!minutes) return 'Not set';
            const duration = parseInt(minutes);
            if (duration >= 60) {
                const hours = Math.floor(duration / 60);
                const mins = duration % 60;
                if (mins === 0) {
                    return hours + (hours === 1 ? ' hour' : ' hours');
                } else {
                    return hours + (hours === 1 ? ' hour' : ' hours') + ' ' + mins + (mins === 1 ? ' minute' : ' minutes');
                }
            } else {
                return duration + (duration === 1 ? ' minute' : ' minutes');
            }
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
        async select(value) {
            if (this.disabled) {
                return;
            }

            const originalValue = this.selectedValue;

            // Update UI optimistically
            this.selectedValue = value;

            // Close dropdown immediately (frontend state)
            this.closeDropdown();

            // Check if parent Alpine component has updateField method (optimistic UI pattern)
            const parentHasOptimistic = this.$parent && typeof this.$parent.updateField === 'function';

            @if($useParent && $itemId)
                try {
                    if (parentHasOptimistic) {
                        // Use parent's optimistic update method
                        await this.$parent.updateField('{{ $field }}', value);
                    } else {
                        // Fallback to direct wire call
                        @if(isset($instanceDate) && $instanceDate)
                            @if($itemType === 'event')
                                await $wire.$parent.call('updateEventField', {{ $itemId }}, '{{ $field }}', value, @js($instanceDate));
                            @else
                                await $wire.$parent.call('updateTaskField', {{ $itemId }}, '{{ $field }}', value, @js($instanceDate));
                            @endif
                        @else
                            @if($itemType === 'event')
                                await $wire.$parent.call('updateEventField', {{ $itemId }}, '{{ $field }}', value);
                            @else
                                await $wire.$parent.call('updateTaskField', {{ $itemId }}, '{{ $field }}', value);
                            @endif
                        @endif
                    }
                } catch (error) {
                    // Rollback on error
                    this.selectedValue = originalValue;
                    console.error('Failed to update field:', error);
                }
            @else
                try {
                    await $wire.updateField('{{ $field }}', value);
                } catch (error) {
                    // Rollback on error
                    this.selectedValue = originalValue;
                    console.error('Failed to update field:', error);
                }
            @endif
        }
    }"
    @mouseenter="handleMouseEnter()"
    @mouseleave="handleMouseLeave()"
    @click.outside="closeDropdown()"
>
    <button
        type="button"
        @click.stop="toggleDropdown()"
        x-bind:class="getTriggerClass()"
        x-bind:disabled="disabled"
    >
        {{ $trigger ?? '' }}
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute {{ $position === 'top' ? 'bottom-full mb-1' : 'top-full mt-1' }} z-50 {{ $dropdownClass }} bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
    >
        {{ $options ?? '' }}
    </div>
</div>

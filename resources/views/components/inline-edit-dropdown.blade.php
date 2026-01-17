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
        getTriggerClass() {
            const baseClass = '{{ $triggerClass }}';
            let classes = baseClass;
            if (this.disabled) {
                classes += ' opacity-50 cursor-not-allowed';
            }
            if (!this.colorMap) {
                return classes;
            }
            const value = this.selectedValue || '';
            const colorClass = value && this.colorMap[value] ? this.colorMap[value] : (this.defaultColorClass || '');
            return classes + (colorClass ? ' ' + colorClass : '');
        },
        init() {
            @if($useParent && $itemId)
                // Listen for backend updates
                window.addEventListener('task-detail-field-updated', (event) => {
                    const { field, value, taskId } = event.detail || {};
                    if (field === '{{ $field }}' && taskId === {{ $itemId }}) {
                        this.selectedValue = value ?? '';
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
        select(value) {
            if (this.disabled) {
                return;
            }
            this.selectedValue = value;
            @if($useParent && $itemId)
                @if(isset($instanceDate) && $instanceDate)
                    // For recurring task/event instances
                    @if($itemType === 'event')
                        $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                            eventId: {{ $itemId }},
                            field: '{{ $field }}',
                            value: value,
                            instanceDate: @js($instanceDate),
                        });
                    @else
                        $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                            taskId: {{ $itemId }},
                            field: '{{ $field }}',
                            value: value,
                            instanceDate: @js($instanceDate),
                        });
                    @endif
                @else
                    // For regular tasks/events
                    @if($itemType === 'event')
                        $wire.$dispatchTo('workspace.show-items', 'update-event-field', {
                            eventId: {{ $itemId }},
                            field: '{{ $field }}',
                            value: value,
                        });
                    @else
                        $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                            taskId: {{ $itemId }},
                            field: '{{ $field }}',
                            value: value,
                        });
                    @endif
                @endif

                // Notify any listeners in the task detail modal so header
                // pills and other UI can stay in sync with this dropdown.
                window.dispatchEvent(new CustomEvent('task-detail-field-updated', {
                    detail: {
                        field: '{{ $field }}',
                        value: value,
                        taskId: {{ $itemId }},
                    }
                }));

                this.closeDropdown();
            @else
                $wire.updateField('{{ $field }}', value).then(() => {
                    this.closeDropdown();
                });
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

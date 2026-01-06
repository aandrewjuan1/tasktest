@props([
    'label' => null,
    'field',
    'value' => null,
    'fullWidth' => true,
    'useParent' => false,
    'itemId' => null,
])

<div
    {{ $attributes->class('relative') }}
    x-data="{
        open: false,
        mouseLeaveTimer: null,
        selectedValue: @js($value),
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
            this.selectedValue = value;
            @if($useParent && $itemId)
                // Fire-and-forget to parent; close optimistically
                $wire.$dispatchTo('workspace.show-items', 'update-task-field', {
                    taskId: {{ $itemId }},
                    field: '{{ $field }}',
                    value: value,
                });
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
    @if($label)
        <div class="flex items-center gap-2 mb-2">
            <flux:heading size="sm">{{ $label }}</flux:heading>
        </div>
    @endif

    <button
        type="button"
        @click.stop="toggleDropdown()"
        class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors {{ $fullWidth ? 'w-full' : '' }}"
    >
        {{ $trigger ?? '' }}
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
    >
        {{ $options ?? '' }}
    </div>
</div>

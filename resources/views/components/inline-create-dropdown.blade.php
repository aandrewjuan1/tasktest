@props([
    'label' => null,
    'fullWidth' => false,
    'dropdownClass' => 'w-48',
])

<div
    {{ $attributes->class('relative') }}
    x-data="{
        open: false,
        mouseLeaveTimer: null,
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
        select(callback = null, shouldClose = true) {
            if (typeof callback === 'function') {
                callback();
            }

            if (shouldClose) {
                this.closeDropdown();
            }
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
        class="absolute bottom-full mb-1 z-50 {{ $dropdownClass }} bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
    >
        {{ $options ?? '' }}
    </div>
</div>

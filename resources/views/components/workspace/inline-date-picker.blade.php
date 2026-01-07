@props([
    'label',
    'model',
    'type' => 'datetime-local',
])

<x-inline-create-dropdown dropdown-class="w-64 p-4">
    <x-slot:trigger>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">{{ $label }}</span>
        <span
            class="text-xs text-zinc-500 dark:text-zinc-400"
            x-text="{{ $model }} || 'Not set'"
        ></span>
    </x-slot:trigger>

    <x-slot:options>
        <div class="space-y-3 px-3 pb-3 pt-1">
            <flux:input x-model="{{ $model }}" type="{{ $type }}" />
            <button
                @click="select(() => {{ $model }} = null)"
                class="w-full px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded"
            >
                Clear
            </button>
        </div>
    </x-slot:options>
</x-inline-create-dropdown>

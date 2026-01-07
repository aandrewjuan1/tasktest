@props([
    'type',
    'label' => 'Tags',
])

<x-inline-create-dropdown dropdown-class="w-64 max-h-60 overflow-y-auto">
    <x-slot:trigger>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        </svg>
        <span class="text-sm font-medium">{{ $label }}</span>
        <span
            class="text-xs text-zinc-500 dark:text-zinc-400"
            x-text="formData.{{ $type }}.tagIds.length > 0 ? formData.{{ $type }}.tagIds.length + ' selected' : 'None'"
        ></span>
    </x-slot:trigger>

    <x-slot:options>
        @foreach($this->availableTags as $tag)
            <label
                wire:key="{{ $type }}-tag-{{ $tag->id }}"
                class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer"
            >
                <input
                    type="checkbox"
                    :checked="isTagSelected({{ $tag->id }}, '{{ $type }}')"
                    @change="select(() => toggleTag({{ $tag->id }}, '{{ $type }}'), false)"
                    class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                />
                <span class="ml-2 text-sm">{{ $tag->name }}</span>
            </label>
        @endforeach

        @if($this->availableTags->isEmpty())
            <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
        @endif
    </x-slot:options>
</x-inline-create-dropdown>

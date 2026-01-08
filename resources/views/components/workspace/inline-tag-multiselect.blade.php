@props([
    'type',
    'label' => 'Tags',
])

<div
    x-data="{
        showCreateInput: false,
        newTagName: '',
        creatingTag: false,
        handleCreateTag() {
            if (!this.newTagName.trim() || this.creatingTag) {
                return;
            }
            this.creatingTag = true;
            const tagName = this.newTagName.trim();
            $wire.$call('createTag', tagName).then((response) => {
                if (response.success) {
                    this.newTagName = '';
                    this.showCreateInput = false;
                    this.creatingTag = false;
                    // Auto-select the newly created tag directly without refresh
                    if (response.tagId) {
                        $dispatch('select-tag', { tagId: response.tagId, type: '{{ $type }}' });
                    }
                } else {
                    this.creatingTag = false;
                }
            }).catch(() => {
                this.creatingTag = false;
            });
        },
        cancelCreate() {
            this.newTagName = '';
            this.showCreateInput = false;
        },
        deletingTag: false,
        handleDeleteTag(tagId) {
            if (this.deletingTag) {
                return;
            }
            this.deletingTag = true;
            $wire.$call('deleteTag', tagId).then((response) => {
                if (response.success) {
                    // Remove tag from selected tags if it's selected
                    $dispatch('remove-tag', { tagId: tagId, type: '{{ $type }}' });
                }
                this.deletingTag = false;
            }).catch(() => {
                this.deletingTag = false;
            });
        }
    }"
>
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
            <!-- Add Tag Button -->
            <button
                x-show="!showCreateInput"
                @click.stop="showCreateInput = true; $nextTick(() => $refs.newTagInput?.focus());"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add Tag</span>
            </button>

            <!-- Create Tag Input -->
            <div
                x-show="showCreateInput"
                x-cloak
                class="px-4 py-2"
                @click.stop
            >
                <div class="flex gap-2 items-center">
                    <input
                        x-ref="newTagInput"
                        type="text"
                        x-model="newTagName"
                        @keydown.enter.prevent="handleCreateTag()"
                        @keydown.escape="cancelCreate()"
                        placeholder="Tag name..."
                        class="flex-1 px-2 py-1 text-sm rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        :disabled="creatingTag"
                    />
                    <button
                        @click.stop="handleCreateTag()"
                        :disabled="!newTagName.trim() || creatingTag"
                        class="p-1 text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        title="Create tag"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                    <button
                        @click.stop="cancelCreate()"
                        :disabled="creatingTag"
                        class="p-1 text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        title="Cancel"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Clear Selected -->
            <button
                x-show="formData.{{ $type }}.tagIds.length"
                @click.stop="$dispatch('clear-tags', { type: '{{ $type }}' })"
                class="w-full flex items-center justify-between px-4 py-1.5 text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
            >
                <span>Clear selected</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Tags List -->
            @foreach($this->availableTags as $tag)
                <div
                    wire:key="{{ $type }}-tag-{{ $tag->id }}"
                    class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 group"
                >
                    <label class="flex items-center flex-1 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="isTagSelected({{ $tag->id }}, '{{ $type }}')"
                            @change="select(() => toggleTag({{ $tag->id }}, '{{ $type }}'), false)"
                            class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                        />
                        <span class="ml-2 text-sm flex-1">{{ $tag->name }}</span>
                    </label>
                    <button
                        @click.stop="handleDeleteTag({{ $tag->id }})"
                        :disabled="deletingTag"
                        class="ml-2 p-1 opacity-0 group-hover:opacity-100 text-zinc-400 hover:text-red-600 dark:hover:text-red-400 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Delete tag"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endforeach

            @if($this->availableTags->isEmpty())
                <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
            @endif
        </x-slot:options>
    </x-inline-create-dropdown>
</div>

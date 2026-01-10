@props([
    'itemId',
    'itemType', // 'task', 'event', or 'project'
    'selectedTagIds' => [],
    'availableTags',
    'dropdownClass' => 'w-64 max-h-60 overflow-y-auto',
    'triggerClass' => 'inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer text-sm font-medium',
    'simpleTrigger' => false,
])

<div
    {{ $attributes->class('relative') }}
    x-data="{
        open: false,
        mouseLeaveTimer: null,
        selectedTagIds: @js($selectedTagIds ?? []),
        showCreateInput: false,
        newTagName: '',
        creatingTag: false,
        deletingTag: false,
        init() {
            // Listen for backend updates
            window.addEventListener('task-detail-field-updated', (event) => {
                const { field, value } = event.detail || {};
                if (field === 'tags') {
                    this.selectedTagIds = value ?? [];
                }
            });
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
        toggleTag(tagId) {
            const index = this.selectedTagIds.indexOf(tagId);
            if (index > -1) {
                this.selectedTagIds.splice(index, 1);
            } else {
                this.selectedTagIds.push(tagId);
            }
            this.updateTags();
        },
        isTagSelected(tagId) {
            return this.selectedTagIds.includes(tagId);
        },
        updateTags() {
            $wire.$dispatchTo('workspace.show-items', 'update-task-tags', {
                itemId: {{ $itemId }},
                itemType: '{{ $itemType }}',
                tagIds: this.selectedTagIds,
            });

            // Notify listeners so UI stays in sync
            window.dispatchEvent(new CustomEvent('task-detail-field-updated', {
                detail: {
                    field: 'tags',
                    value: this.selectedTagIds,
                }
            }));
        },
        async handleCreateTag() {
            if (!this.newTagName.trim() || this.creatingTag) {
                return;
            }
            this.creatingTag = true;
            const tagName = this.newTagName.trim();
            const response = await $wire.$call('createTag', tagName);
            if (response.success) {
                this.newTagName = '';
                this.showCreateInput = false;
                this.creatingTag = false;
                // Dispatch event for newly created tag so UI can update
                if (response.tagId && response.tagName) {
                    window.dispatchEvent(new CustomEvent('tag-created', {
                        detail: {
                            tagId: response.tagId,
                            tagName: response.tagName,
                        }
                    }));
                }
                // Auto-select the newly created tag
                if (response.tagId && !this.selectedTagIds.includes(response.tagId)) {
                    this.selectedTagIds.push(response.tagId);
                    this.updateTags();
                }
            } else {
                this.creatingTag = false;
            }
        },
        cancelCreate() {
            this.newTagName = '';
            this.showCreateInput = false;
        },
        async handleDeleteTag(tagId) {
            if (this.deletingTag) {
                return;
            }
            this.deletingTag = true;
            const response = await $wire.$call('deleteTag', tagId);
            if (response.success) {
                // Remove tag from selected tags if it's selected
                const index = this.selectedTagIds.indexOf(tagId);
                if (index > -1) {
                    this.selectedTagIds.splice(index, 1);
                    this.updateTags();
                }
            }
            this.deletingTag = false;
        },
        clearTags() {
            this.selectedTagIds = [];
            this.updateTags();
        }
    }"
    @mouseenter="handleMouseEnter()"
    @mouseleave="handleMouseLeave()"
    @click.outside="closeDropdown()"
>
    <button
        type="button"
        @click.stop="toggleDropdown()"
        class="{{ $triggerClass }}"
    >
        @if($simpleTrigger)
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        @else
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M3 5a2 2 0 012-2h4l2 2h6a2 2 0 012 2v2.586a1 1 0 01-.293.707l-7.414 7.414a2 2 0 01-2.828 0L3.293 9.707A1 1 0 013 9V5z" />
            </svg>
            <span>Tags</span>
            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="selectedTagIds.length > 0 ? selectedTagIds.length + ' selected' : 'None'"></span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute top-full mt-1 z-50 {{ $dropdownClass }} bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1"
    >
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
            x-show="selectedTagIds.length > 0"
            @click.stop="clearTags()"
            class="w-full flex items-center justify-between px-4 py-1.5 text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
        >
            <span>Clear selected</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Tags List -->
        @foreach($availableTags as $tag)
            <div
                wire:key="edit-tag-{{ $itemType }}-{{ $tag->id }}"
                class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 group"
            >
                <label class="flex items-center flex-1 cursor-pointer">
                    <input
                        type="checkbox"
                        :checked="isTagSelected({{ $tag->id }})"
                        @change="toggleTag({{ $tag->id }})"
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

        @if($availableTags->isEmpty())
            <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
        @endif
    </div>
</div>

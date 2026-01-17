@php
    $task = \App\Models\Task::find($taskId);
    $canComment = $task ? $task->canUserComment(auth()->user()) : false;
@endphp

<div
    {{ $attributes->class('mt-4 border-t border-zinc-200 dark:border-zinc-800 pt-4') }}
    x-data="{
        comments: @js($comments),
        canComment: @js($canComment),
        currentUserId: @js(auth()->id()),
        submitting: false,
        editingId: null,
        editingContent: '',
        showInput: false,
        newComment: '',
        showDeleteCommentConfirm: false,
        commentIdToDelete: null,
        toggleInput() {
            if (!this.canComment) return;
            this.showInput = true;
            this.$nextTick(() => this.$refs.commentInput?.focus());
        },
        hideInput() {
            this.showInput = false;
            this.newComment = '';
        },
        addComment() {
            const trimmed = (this.newComment || '').trim();
            if (!this.canComment || !trimmed || this.submitting) return;
            this.submitting = true;

            $wire.$dispatchTo('workspace.show-items', 'add-task-comment', {
                taskId: {{ $taskId }},
                content: trimmed,
            });

            this.comments.unshift({
                id: Date.now(),
                content: trimmed,
                created_at: 'Just now',
                is_edited: false,
                edited_at: null,
                user_name: @js(auth()->user()?->name ?? 'You'),
                user_id: this.currentUserId,
                can_manage: true,
            });

            this.newComment = '';
            this.submitting = false;
            this.hideInput();
        },
        startEdit(comment) {
            if (!comment.can_manage) return;
            this.editingId = comment.id;
            this.editingContent = comment.content;
            this.$nextTick(() => {
                const editContainer = document.querySelector(`[data-comment-id='${comment.id}']`);
                if (editContainer) {
                    const textarea = editContainer.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                        // Move cursor to end
                        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                    }
                }
            });
        },
        cancelEdit() {
            this.editingId = null;
            this.editingContent = '';
        },
        saveEdit(comment) {
            const trimmed = (this.editingContent || '').trim();
            if (!comment.can_manage || !trimmed) {
                return;
            }

            comment.content = trimmed;
            comment.is_edited = true;
            comment.edited_at = 'Just now';

            $wire.$dispatchTo('workspace.show-items', 'update-task-comment', {
                taskId: {{ $taskId }},
                commentId: comment.id,
                content: trimmed,
            });

            this.cancelEdit();
        },
        deleteComment(comment) {
            if (!comment.can_manage) return;

            this.commentIdToDelete = comment.id;
            this.showDeleteCommentConfirm = true;
        },
        handleEditEnterKey(event, comment) {
            if (event.shiftKey) {
                // Shift+Enter: allow default behavior (new line)
                return;
            }
            // Enter: prevent default and save
            event.preventDefault();
            this.saveEdit(comment);
        },
        handleAddCommentEnterKey(event) {
            if (event.shiftKey) {
                // Shift+Enter: allow default behavior (new line)
                return;
            }
            // Enter: prevent default and add comment
            event.preventDefault();
            this.addComment();
        }
    }"
>
    <div class="flex items-center justify-between gap-2 mb-3">
        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-6 4h4m7 2H6a2 2 0 01-2-2V6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2z" />
            </svg>
            Comments
        </flux:heading>

        @if($canComment)
            <button
                type="button"
                class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
                @click="toggleInput()"
            >
                <span>Add comments</span>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-xs">+</span>
            </button>
        @endif
    </div>

    <div class="space-y-3">
        <template x-if="comments.length === 0">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                No comments yet.
            </p>
        </template>

        <template x-for="comment in comments" :key="comment.id">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/60 dark:bg-zinc-900/40 px-3 py-2.5">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                            <span class="font-medium text-zinc-700 dark:text-zinc-200" x-text="comment.user_name"></span>
                            <span x-text="comment.created_at"></span>
                            <span
                                x-show="comment.is_edited"
                                class="text-[11px] text-zinc-400 dark:text-zinc-500"
                            >
                                · edited
                            </span>
                        </div>

                        <div x-show="editingId !== comment.id">
                            <p class="text-sm text-zinc-700 dark:text-zinc-200 whitespace-pre-line" x-text="comment.content"></p>
                        </div>

                        <div
                            x-show="editingId === comment.id"
                            x-cloak
                            x-bind:data-comment-id="comment.id"
                            class="mt-1"
                            @click.away="cancelEdit()"
                        >
                            <div class="space-y-2">
                                <flux:textarea
                                    rows="3"
                                    x-model="editingContent"
                                    @keydown.enter="handleEditEnterKey($event, comment)"
                                    @keydown.escape="cancelEdit()"
                                />
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white disabled:opacity-40 disabled:cursor-not-allowed hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                                        @click="saveEdit(comment)"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 12h9m0 0l-4-4m4 4l-4 4m9-8v8"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1" x-show="comment.can_manage && editingId !== comment.id">
                        <button
                            type="button"
                            class="text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                            @click="startEdit(comment)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="text-xs text-red-500 hover:text-red-600"
                            @click="deleteComment(comment)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="mt-4">
        <div
            x-show="canComment && showInput"
            x-cloak
            @click.away="hideInput()"
            class="space-y-2"
        >
            <flux:textarea
                rows="3"
                placeholder="Write a comment..."
                x-ref="commentInput"
                x-model="newComment"
                @keydown.enter="handleAddCommentEnterKey($event)"
            />
            <div class="flex justify-end">
                <button
                    type="button"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white disabled:opacity-40 disabled:cursor-not-allowed hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                    @click="addComment()"
                    x-bind:disabled="!newComment.trim().length || submitting"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M5 12h9m0 0l-4-4m4 4l-4 4m9-8v8" />
                    </svg>
                </button>
            </div>
        </div>

        <p x-show="!canComment" class="text-xs text-zinc-500 dark:text-zinc-400">
            You can view comments on this task but cannot add new ones.
        </p>
    </div>

    <!-- Delete Comment Confirmation Modal -->
    <flux:modal
        x-model="showDeleteCommentConfirm"
        class="max-w-md my-10 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl bg-white dark:bg-zinc-900"
    >
        <flux:heading size="lg" class="mb-2 text-red-600 dark:text-red-400">Delete Comment</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
            Are you sure you want to delete this comment? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
            <flux:button
                variant="ghost"
                @click="showDeleteCommentConfirm = false"
            >
                Cancel
            </flux:button>
            <flux:button
                variant="danger"
                @click="
                    const id = commentIdToDelete;
                    if (id) {
                        comments = comments.filter(c => c.id !== id);
                        showDeleteCommentConfirm = false;
                        commentIdToDelete = null;
                        $wire.$dispatchTo('workspace.show-items', 'delete-task-comment', {
                            taskId: {{ $taskId }},
                            commentId: id,
                        });
                    }
                "
            >
                Delete
            </flux:button>
        </div>
    </flux:modal>
</div>

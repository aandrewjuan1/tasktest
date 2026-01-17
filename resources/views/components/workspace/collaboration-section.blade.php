@props(['taskId', 'collaborators'])

<div
    {{ $attributes->class('border-t border-zinc-200 dark:border-zinc-700 pt-4 mt-4') }}
    x-data="{
        collaborators: @js($collaborators),
        showAddForm: false,
        newEmail: '',
        newPermission: 'view',
        errorMessage: null,
        showRemoveCollaboratorConfirm: false,
        collaborationIdToRemove: null,
        lastAddedEmail: null,
        init() {
            const handleValidationError = (event) => {
                const data = Array.isArray(event) ? event[0] : (event.detail || event);
                const { message, email } = data || {};

                if (email && this.lastAddedEmail === email.toLowerCase()) {
                    this.collaborators = this.collaborators.filter(c => {
                        return !(c.is_optimistic && c.user_email === email.toLowerCase());
                    });
                }

                this.errorMessage = message || 'The email address is invalid or does not exist.';
                this.showAddForm = true;
                this.newEmail = email || '';
                this.lastAddedEmail = null;
            };

            window.addEventListener('collaborator-validation-error', handleValidationError);

            document.addEventListener('livewire:init', () => {
                Livewire.on('collaborator-validation-error', handleValidationError);
            });
        },
        toggleAddForm() {
            this.showAddForm = !this.showAddForm;
            this.newEmail = '';
            this.newPermission = 'view';
            this.errorMessage = null;
            if (this.showAddForm) {
                this.$nextTick(() => {
                    const input = this.$refs.emailInput;
                    if (input) {
                        input.focus();
                    }
                });
            }
        },
        addCollaborator() {
            const trimmedEmail = (this.newEmail || '').trim().toLowerCase();
            if (!trimmedEmail) {
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(trimmedEmail)) {
                this.errorMessage = 'Please enter a valid email address';
                return;
            }

            this.errorMessage = null;
            this.lastAddedEmail = trimmedEmail;

            const tempId = Date.now();
            const optimisticCollaborator = {
                id: tempId,
                user_id: null,
                user_name: trimmedEmail,
                user_email: trimmedEmail,
                permission: this.newPermission,
                is_optimistic: true,
            };

            this.collaborators.unshift(optimisticCollaborator);

            $wire.$dispatchTo('workspace.show-items', 'add-task-collaborator', {
                taskId: {{ $taskId }},
                email: trimmedEmail,
                permission: this.newPermission,
            });

            this.newEmail = '';
            this.newPermission = 'view';
            this.showAddForm = false;
        },
        removeCollaborator(collaborationId) {
            this.collaborationIdToRemove = collaborationId;
            this.showRemoveCollaboratorConfirm = true;
        },
        confirmRemove() {
            const id = this.collaborationIdToRemove;
            if (id) {
                this.collaborators = this.collaborators.filter(c => c.id !== id);
                $wire.$dispatchTo('workspace.show-items', 'remove-task-collaborator', {
                    taskId: {{ $taskId }},
                    collaborationId: id,
                });
                this.showRemoveCollaboratorConfirm = false;
                this.collaborationIdToRemove = null;
            }
        },
        getPermissionBadgeClass(permission) {
            return {
                'view': 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300',
                'edit': 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
            }[permission] || 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300';
        },
        getPermissionLabel(permission) {
            return {
                'view': 'View',
                'edit': 'Edit',
            }[permission] || permission;
        }
    }"
>
    <div class="flex items-center justify-between gap-2 mb-3">
        <flux:heading size="sm" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
            <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8zm6 8H6" />
            </svg>
            Collaboration
        </flux:heading>

        <button
            type="button"
            class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
            @click="toggleAddForm()"
        >
            <span>Add collaborator</span>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-xs">+</span>
        </button>
    </div>

    <div
        x-show="showAddForm"
        x-cloak
        @click.away="showAddForm = false"
        class="mb-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/60 dark:bg-zinc-900/40 space-y-3"
    >
        <div class="space-y-2">
            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Email</label>
            <flux:input
                x-ref="emailInput"
                x-model="newEmail"
                type="email"
                placeholder="user@example.com"
                @keydown.enter.prevent="addCollaborator()"
                @keydown.escape="toggleAddForm()"
            />
        </div>

        <div class="space-y-2">
            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Permission</label>
            <select
                x-model="newPermission"
                class="w-full px-3 py-2 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <option value="view">View</option>
                <option value="edit">Edit</option>
            </select>
        </div>

        <div x-show="errorMessage" class="mt-2">
            <p class="text-sm text-red-600 dark:text-red-400" x-text="errorMessage"></p>
        </div>

        <div class="flex justify-end gap-2">
            <button
                type="button"
                @click="toggleAddForm()"
                class="px-4 py-2 text-sm font-medium rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
            >
                Cancel
            </button>
            <button
                type="button"
                @click="addCollaborator()"
                x-bind:disabled="!newEmail.trim()"
                class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                Add
            </button>
        </div>
    </div>

    <div class="space-y-2">
        <template x-if="collaborators.length === 0">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                No collaborators yet. Add someone to share this task.
            </p>
        </template>

        <template x-for="collaborator in collaborators" :key="collaborator.id">
            <div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/60 dark:bg-zinc-900/40">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="collaborator.user_name"></p>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate" x-text="collaborator.user_email"></p>
                </div>

                <div class="flex items-center gap-2">
                    <div
                        x-data="{
                            open: false,
                            pendingPermission: null,
                            selectPermission(newPermission) {
                                // Store selection but don't update state yet
                                this.pendingPermission = newPermission;
                                // Close dropdown immediately (no state changes = no delay)
                                this.open = false;
                            },
                            applyPendingChange() {
                                if (!this.pendingPermission || this.pendingPermission === collaborator.permission) {
                                    this.pendingPermission = null;
                                    return;
                                }

                                const newPermission = this.pendingPermission;
                                this.pendingPermission = null;

                                // Update UI optimistically
                                collaborator.permission = newPermission;

                                // Dispatch to backend (non-blocking)
                                $wire.$dispatchTo('workspace.show-items', 'update-task-collaborator-permission', {
                                    taskId: {{ $taskId }},
                                    collaborationId: collaborator.id,
                                    permission: newPermission,
                                });
                            }
                        }"
                        class="relative"
                        @click.outside="open = false"
                        x-init="
                            $watch('open', (isOpen) => {
                                if (!isOpen && pendingPermission) {
                                    applyPendingChange();
                                }
                            });
                        "
                        class="relative"
                    >
                        <button
                            type="button"
                            @click.stop="open = !open"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                            :class="getPermissionBadgeClass(collaborator.permission)"
                        >
                            <span x-text="getPermissionLabel(collaborator.permission)"></span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            class="absolute right-0 mt-1 w-32 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 z-10"
                        >
                            <button
                                @click="selectPermission('view')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="collaborator.permission === 'view' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                View
                            </button>
                            <button
                                @click="selectPermission('edit')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                :class="collaborator.permission === 'edit' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ''"
                            >
                                Edit
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="removeCollaborator(collaborator.id)"
                        class="text-xs text-red-500 hover:text-red-600 dark:hover:text-red-400"
                    >
                        Remove
                    </button>
                </div>
            </div>
        </template>
    </div>

    <flux:modal
        x-model="showRemoveCollaboratorConfirm"
        class="max-w-md my-10 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl bg-white dark:bg-zinc-900"
    >
        <flux:heading size="lg" class="mb-2 text-red-600 dark:text-red-400">Remove Collaborator</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
            Are you sure you want to remove this collaborator? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
            <flux:button
                variant="ghost"
                @click="showRemoveCollaboratorConfirm = false"
            >
                Cancel
            </flux:button>
            <flux:button
                variant="danger"
                @click="confirmRemove()"
            >
                Remove
            </flux:button>
        </div>
    </flux:modal>
</div>

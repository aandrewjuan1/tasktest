<?php

use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;
use function Livewire\Volt\title;

layout('components.layouts.app');
title(__('Tasks'));

new class extends Component {
    /**
     * The task creation form.
     *
     * @var array{
     *     title: string,
     *     description: ?string,
     *     subject: ?string,
     *     type: string,
     *     priority: string,
     *     status: string,
     *     deadline: ?string,
     *     estimated_minutes: ?int,
     * }
     */
    public array $form = [];

    /**
     * Prime the form when the component mounts.
     */
    public function mount(): void
    {
        $this->resetFormState();
    }

    /**
     * Tasks grouped by status for the authenticated user.
     *
     * @return array<string, Collection<int, Task>>
     */
    #[Computed]
    public function tasksByStatus(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        return Task::query()
            ->where('user_id', $userId)
            ->orderByRaw(
                "CASE status WHEN 'to-do' THEN 1 WHEN 'in-progress' THEN 2 ELSE 3 END"
            )
            ->orderBy('deadline')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('status')
            ->map(
                fn (Collection $tasks): Collection => $tasks->values()
            )
            ->all();
    }

    /**
     * Create a new task.
     */
    public function createTask(): void
    {
        $validated = $this->validate($this->rules());
        $attributes = $validated['form'];

        $attributes['deadline'] = $attributes['deadline']
            ? Carbon::parse($attributes['deadline'], config('app.timezone'))
            : null;

        $attributes['estimated_minutes'] = $attributes['estimated_minutes']
            ? (int) $attributes['estimated_minutes']
            : null;

        $attributes['completed_at'] = $attributes['status'] === 'completed'
            ? now()
            : null;

        Auth::user()
            ?->tasks()
            ->create($attributes);

        $this->resetFormState();
        $this->dispatch('task-created');
    }

    /**
     * Update a task's status.
     */
    public function updateStatus(string $taskId, string $status): void
    {
        if (! in_array($status, Task::STATUSES, true)) {
            return;
        }

        $task = Auth::user()
            ?->tasks()
            ->whereKey($taskId)
            ->firstOrFail();

        $task->forceFill([
            'status' => $status,
            'completed_at' => $status === 'completed'
                ? now()
                : null,
        ])->save();

        $this->dispatch('task-updated');
    }

    /**
     * Delete a task.
     */
    public function deleteTask(string $taskId): void
    {
        Auth::user()
            ?->tasks()
            ->whereKey($taskId)
            ->firstOrFail()
            ->delete();

        $this->dispatch('task-deleted');
    }

    /**
     * Reset the form state.
     */
    protected function resetFormState(): void
    {
        $this->resetErrorBag();

        $this->form = [
            'title' => '',
            'description' => null,
            'subject' => null,
            'type' => Task::TYPES[0],
            'priority' => Task::PRIORITIES[1],
            'status' => Task::STATUSES[0],
            'deadline' => null,
            'estimated_minutes' => null,
        ];
    }

    /**
     * Validation rules for the task form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.title' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.subject' => ['nullable', 'string', 'max:120'],
            'form.type' => ['required', Rule::in(Task::TYPES)],
            'form.priority' => ['required', Rule::in(Task::PRIORITIES)],
            'form.status' => ['required', Rule::in(Task::STATUSES)],
            'form.deadline' => ['nullable', 'date'],
            'form.estimated_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
        ];
    }
}; ?>

<div class="space-y-8">
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Create a task') }}</flux:heading>
                <flux:subheading>{{ __('Capture upcoming assignments, quizzes, or study blocks to stay organized.') }}</flux:subheading>
            </div>
        </div>

        <form wire:submit="createTask" class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <flux:input wire:model.live="form.title" :label="__('Title')" type="text" required />
                @error('form.title')
                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <flux:input wire:model="form.subject" :label="__('Subject')" type="text" />

            <flux:select wire:model="form.type" :label="__('Type')">
                @foreach (Task::TYPES as $type)
                    <flux:select.option :value="$type">{{ \Illuminate\Support\Str::headline($type) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.priority" :label="__('Priority')">
                @foreach (Task::PRIORITIES as $priority)
                    <flux:select.option :value="$priority">{{ \Illuminate\Support\Str::headline($priority) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.status" :label="__('Status')">
                @foreach (Task::STATUSES as $status)
                    <flux:select.option :value="$status">{{ \Illuminate\Support\Str::headline($status) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.deadline" :label="__('Deadline')" type="datetime-local" />

            <flux:input wire:model="form.estimated_minutes" :label="__('Estimated minutes')" type="number" min="15" step="5" />

            <div class="md:col-span-2">
                <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />
            </div>

            <div class="md:col-span-2 flex items-center justify-end gap-3">
                <x-action-message class="text-sm text-emerald-500" on="task-created">
                    {{ __('Task added!') }}
                </x-action-message>

                <flux:button variant="primary" type="submit" class="min-w-32">
                    <span wire:loading.remove wire:target="createTask">{{ __('Save task') }}</span>
                    <span wire:loading wire:target="createTask">{{ __('Saving...') }}</span>
                </flux:button>
            </div>
        </form>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach (Task::STATUSES as $status)
            @php
                $tasks = $this->tasksByStatus[$status] ?? collect();
            @endphp

            <div class="flex min-h-[300px] flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <flux:heading size="md">{{ \Illuminate\Support\Str::headline($status) }}</flux:heading>
                    <flux:badge variant="muted">{{ $tasks->count() }}</flux:badge>
                </div>

                <div class="flex-1 space-y-3 p-4">
                    @forelse ($tasks as $task)
                        <div class="rounded-lg border border-neutral-200 bg-white p-3 shadow-sm dark:border-neutral-700 dark:bg-zinc-800">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $task->title }}</h3>
                                    @if ($task->subject)
                                        <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $task->subject }}</p>
                                    @endif
                                </div>

                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />

                                    <flux:menu class="w-40">
                                        <flux:menu.group>
                                            @foreach (Task::STATUSES as $option)
                                                <flux:menu.item wire:click="updateStatus('{{ $task->getKey() }}', '{{ $option }}')" icon="arrows-up-down">
                                                    {{ \Illuminate\Support\Str::headline($option) }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu.group>

                                        <flux:menu.separator />

                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash"
                                            wire:click="deleteTask('{{ $task->getKey() }}')"
                                            wire:confirm="{{ __('Are you sure you want to delete this task?') }}"
                                        >
                                            {{ __('Delete task') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                            @if ($task->description)
                                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                    {{ $task->description }}
                                </p>
                            @endif

                            <dl class="mt-3 grid gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="flag" size="xs" />
                                    <span>{{ __('Priority:') }} {{ \Illuminate\Support\Str::headline($task->priority) }}</span>
                                </div>

                                @if ($task->deadline)
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="calendar-days" size="xs" />
                                        <span>{{ $task->deadline->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                                    </div>
                                @endif

                                @if ($task->estimated_minutes)
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="clock" size="xs" />
                                        <span>{{ __('Est. :minutes min', ['minutes' => $task->estimated_minutes]) }}</span>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @empty
                        <div class="flex h-full flex-1 items-center justify-center rounded-lg border border-dashed border-neutral-300 p-6 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                            {{ __('No tasks yet. Create one above to get started.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

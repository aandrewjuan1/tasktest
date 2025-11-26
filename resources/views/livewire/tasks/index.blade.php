<?php

use App\Models\Task;
use App\Models\Tag;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use function Livewire\Volt\{state, computed};

new
#[Title('Tasks')]
class extends Component {
    public string $view = 'list';
    public string $search = '';
    public string $statusFilter = '';
    public string $priorityFilter = '';
    public string $tagFilter = '';

    // Create/Edit task properties
    public bool $showTaskModal = false;
    public ?int $editingTaskId = null;
    public string $taskTitle = '';
    public string $taskDescription = '';
    public string $taskStatus = 'to_do';
    public string $taskPriority = 'medium';
    public string $taskComplexity = 'simple';
    public int $taskDuration = 30;
    public string $taskStartDate = '';
    public string $taskEndDate = '';
    public ?int $taskProjectId = null;
    public array $selectedTags = [];

    // Inline editing
    public ?int $editingInlineTaskId = null;
    public string $inlineTitle = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->taskStartDate = now()->format('Y-m-d');
        $this->taskEndDate = now()->addDay()->format('Y-m-d');
    }

    /**
     * Get filtered tasks.
     */
    public function tasks()
    {
        return Task::query()
            ->where('user_id', Auth::id())
            ->with(['project', 'tags'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->priorityFilter, function ($query) {
                $query->where('priority', $this->priorityFilter);
            })
            ->when($this->tagFilter, function ($query) {
                $query->whereHas('tags', function ($q) {
                    $q->where('tags.id', $this->tagFilter);
                });
            })
            ->orderBy('end_date', 'asc')
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get all available tags.
     */
    public function allTags()
    {
        return Tag::all();
    }

    /**
     * Get all user's projects.
     */
    public function projects()
    {
        return Project::where('user_id', Auth::id())->get();
    }

    /**
     * Switch between views.
     */
    public function switchView(string $view): void
    {
        $this->view = $view;
    }

    /**
     * Open modal to create new task.
     */
    public function openCreateModal(): void
    {
        $this->reset(['editingTaskId', 'taskTitle', 'taskDescription', 'taskStatus', 'taskPriority', 'taskComplexity', 'taskDuration', 'taskProjectId', 'selectedTags']);
        $this->taskStatus = 'to_do';
        $this->taskPriority = 'medium';
        $this->taskComplexity = 'simple';
        $this->taskDuration = 30;
        $this->taskStartDate = now()->format('Y-m-d');
        $this->taskEndDate = now()->addDay()->format('Y-m-d');
        $this->showTaskModal = true;
    }

    /**
     * Open modal to edit task.
     */
    public function editTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        if ($task->user_id !== Auth::id()) {
            return;
        }

        $this->editingTaskId = $task->id;
        $this->taskTitle = $task->title;
        $this->taskDescription = $task->description ?? '';
        $this->taskStatus = $task->status;
        $this->taskPriority = $task->priority;
        $this->taskComplexity = $task->complexity;
        $this->taskDuration = $task->duration;
        $this->taskStartDate = $task->start_date->format('Y-m-d');
        $this->taskEndDate = $task->end_date->format('Y-m-d');
        $this->taskProjectId = $task->project_id;
        $this->selectedTags = $task->tags->pluck('id')->toArray();
        $this->showTaskModal = true;
    }

    /**
     * Save task (create or update).
     */
    public function saveTask(): void
    {
        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskDescription' => ['nullable', 'string'],
            'taskStatus' => ['required', 'in:to_do,doing,done'],
            'taskPriority' => ['required', 'in:low,medium,high,urgent'],
            'taskComplexity' => ['required', 'in:simple,moderate,complex'],
            'taskDuration' => ['required', 'integer', 'min:1'],
            'taskStartDate' => ['required', 'date'],
            'taskEndDate' => ['required', 'date', 'after_or_equal:taskStartDate'],
            'taskProjectId' => ['nullable', 'exists:projects,id'],
        ]);

        $taskData = [
            'user_id' => Auth::id(),
            'title' => $validated['taskTitle'],
            'description' => $validated['taskDescription'],
            'status' => $validated['taskStatus'],
            'priority' => $validated['taskPriority'],
            'complexity' => $validated['taskComplexity'],
            'duration' => $validated['taskDuration'],
            'start_date' => $validated['taskStartDate'],
            'end_date' => $validated['taskEndDate'],
            'project_id' => $validated['taskProjectId'],
        ];

        if ($this->editingTaskId) {
            $task = Task::findOrFail($this->editingTaskId);

            if ($task->user_id !== Auth::id()) {
                return;
            }

            $task->update($taskData);
        } else {
            $task = Task::create($taskData);
        }

        // Sync tags
        $task->tags()->sync($this->selectedTags);

        $this->showTaskModal = false;
        $this->reset(['editingTaskId', 'taskTitle', 'taskDescription', 'selectedTags']);
    }

    /**
     * Delete task.
     */
    public function deleteTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        if ($task->user_id !== Auth::id()) {
            return;
        }

        $task->delete();
    }

    /**
     * Toggle task completion.
     */
    public function toggleComplete(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        if ($task->user_id !== Auth::id()) {
            return;
        }

        if ($task->status === 'done') {
            $task->update([
                'status' => 'to_do',
                'completed_at' => null,
            ]);
        } else {
            $task->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Update task status (for kanban).
     */
    public function updateTaskStatus(int $taskId, string $newStatus): void
    {
        $task = Task::findOrFail($taskId);

        if ($task->user_id !== Auth::id()) {
            return;
        }

        $task->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'done' ? now() : null,
        ]);
    }

    /**
     * Start inline editing.
     */
    public function startInlineEdit(int $taskId, string $currentTitle): void
    {
        $this->editingInlineTaskId = $taskId;
        $this->inlineTitle = $currentTitle;
    }

    /**
     * Save inline edit.
     */
    public function saveInlineEdit(): void
    {
        if (!$this->editingInlineTaskId) {
            return;
        }

        $validated = $this->validate([
            'inlineTitle' => ['required', 'string', 'max:255'],
        ]);

        $task = Task::findOrFail($this->editingInlineTaskId);

        if ($task->user_id !== Auth::id()) {
            return;
        }

        $task->update(['title' => $validated['inlineTitle']]);

        $this->editingInlineTaskId = null;
        $this->inlineTitle = '';
    }

    /**
     * Cancel inline editing.
     */
    public function cancelInlineEdit(): void
    {
        $this->editingInlineTaskId = null;
        $this->inlineTitle = '';
    }

    /**
     * Get status label.
     */
    public function getStatusLabel(string $status): string
    {
        return match($status) {
            'to_do' => 'To Do',
            'doing' => 'In Progress',
            'done' => 'Done',
            default => $status,
        };
    }

    /**
     * Get priority badge variant.
     */
    public function getPriorityVariant(string $priority): string
    {
        return match($priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'medium' => 'primary',
            'low' => 'ghost',
            default => 'ghost',
        };
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-4">
        {{-- Header Section --}}
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            {{-- View Switcher & Actions --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-2">
                    <flux:button
                        wire:click="switchView('list')"
                        :variant="$view === 'list' ? 'primary' : 'ghost'"
                        size="sm"
                        icon="list-bullet"
                    >
                        List
                    </flux:button>
                    <flux:button
                        wire:click="switchView('kanban')"
                        :variant="$view === 'kanban' ? 'primary' : 'ghost'"
                        size="sm"
                        icon="view-columns"
                    >
                        Kanban
                    </flux:button>
                    <flux:button
                        wire:click="switchView('timeline')"
                        :variant="$view === 'timeline' ? 'primary' : 'ghost'"
                        size="sm"
                        icon="calendar-days"
                    >
                        Timeline
                    </flux:button>
                </div>

                <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                    New Task
                </flux:button>
            </div>

            {{-- Search & Filters --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search tasks..."
                    icon="magnifying-glass"
                />

                <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                    <option value="">All Statuses</option>
                    <option value="to_do">To Do</option>
                    <option value="doing">In Progress</option>
                    <option value="done">Done</option>
                </flux:select>

                <flux:select wire:model.live="priorityFilter" placeholder="All Priorities">
                    <option value="">All Priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </flux:select>

                <flux:select wire:model.live="tagFilter" placeholder="All Tags">
                    <option value="">All Tags</option>
                    @foreach($this->allTags() as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Content Section --}}
        <div class="flex-1 overflow-hidden">
            @if($view === 'list')
                {{-- List View --}}
                <div class="h-full overflow-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="min-w-full">
                        <table class="w-full">
                            <thead class="sticky top-0 border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        <flux:checkbox wire:model="selectAll" class="inline" />
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">Title</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">Priority</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">Due Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">Tags</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse($this->tasks() as $task)
                                    <tr wire:key="task-{{ $task->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3">
                                            <flux:checkbox
                                                wire:click="toggleComplete({{ $task->id }})"
                                                :checked="$task->status === 'done'"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($editingInlineTaskId === $task->id)
                                                <div class="flex items-center gap-2">
                                                    <flux:input
                                                        wire:model="inlineTitle"
                                                        wire:keydown.enter="saveInlineEdit"
                                                        wire:keydown.escape="cancelInlineEdit"
                                                        class="flex-1"
                                                    />
                                                    <flux:button wire:click="saveInlineEdit" size="sm" icon="check" variant="primary" />
                                                    <flux:button wire:click="cancelInlineEdit" size="sm" icon="x-mark" variant="ghost" />
                                                </div>
                                            @else
                                                <div
                                                    class="cursor-pointer text-sm font-medium text-zinc-900 dark:text-zinc-100"
                                                    wire:click="startInlineEdit({{ $task->id }}, '{{ addslashes($task->title) }}')"
                                                >
                                                    {{ $task->title }}
                                                </div>
                                                @if($task->description)
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ Str::limit($task->description, 50) }}
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:badge size="sm">{{ $this->getStatusLabel($task->status) }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:badge :variant="$this->getPriorityVariant($task->priority)" size="sm">
                                                {{ ucfirst($task->priority) }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">
                                            {{ $task->end_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($task->tags as $tag)
                                                    <flux:badge size="sm" variant="ghost">{{ $tag->name }}</flux:badge>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex gap-2">
                                                <flux:button wire:click="editTask({{ $task->id }})" size="sm" icon="pencil" variant="ghost" />
                                                <flux:button
                                                    wire:click="deleteTask({{ $task->id }})"
                                                    wire:confirm="Are you sure you want to delete this task?"
                                                    size="sm"
                                                    icon="trash"
                                                    variant="danger"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                            No tasks found. Create your first task to get started!
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif($view === 'kanban')
                {{-- Kanban Board View --}}
                <div class="flex h-full gap-4 overflow-x-auto pb-4">
                    @foreach(['to_do' => 'To Do', 'doing' => 'In Progress', 'done' => 'Done'] as $statusKey => $statusLabel)
                        <div class="flex min-w-[300px] flex-1 flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ $statusLabel }}
                                </h3>
                                <flux:badge size="sm">
                                    {{ $this->tasks()->where('status', $statusKey)->count() }}
                                </flux:badge>
                            </div>

                            <div class="flex flex-1 flex-col gap-2 overflow-y-auto">
                                @foreach($this->tasks()->where('status', $statusKey) as $task)
                                    <div
                                        wire:key="kanban-task-{{ $task->id }}"
                                        class="cursor-pointer rounded-lg border border-zinc-200 bg-zinc-50 p-3 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600"
                                        wire:click="editTask({{ $task->id }})"
                                    >
                                        <div class="mb-2 flex items-start justify-between">
                                            <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $task->title }}
                                            </h4>
                                            <flux:badge :variant="$this->getPriorityVariant($task->priority)" size="sm">
                                                {{ ucfirst($task->priority) }}
                                            </flux:badge>
                                        </div>

                                        @if($task->description)
                                            <p class="mb-2 text-xs text-zinc-600 dark:text-zinc-400">
                                                {{ Str::limit($task->description, 60) }}
                                            </p>
                                        @endif

                                        <div class="mb-2 flex flex-wrap gap-1">
                                            @foreach($task->tags as $tag)
                                                <flux:badge size="sm" variant="ghost">{{ $tag->name }}</flux:badge>
                                            @endforeach
                                        </div>

                                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                            <span>{{ $task->end_date->format('M d') }}</span>
                                            <span>{{ $task->duration }}m</span>
                                        </div>
                                    </div>
                                @endforeach

                                @if($this->tasks()->where('status', $statusKey)->isEmpty())
                                    <div class="flex flex-1 items-center justify-center rounded-lg border-2 border-dashed border-zinc-200 p-4 text-xs text-zinc-400 dark:border-zinc-700 dark:text-zinc-500">
                                        No tasks
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

            @else
                {{-- Weekly Timeline View --}}
                <div class="h-full overflow-auto rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Weekly Timeline
                        </h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ now()->startOfWeek()->format('M d') }} - {{ now()->endOfWeek()->format('M d, Y') }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        @php
                            $daysOfWeek = collect(range(0, 6))->map(fn($day) => now()->startOfWeek()->addDays($day));
                        @endphp

                        @foreach($daysOfWeek as $day)
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="mb-2 font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $day->format('l, M d') }}
                                </div>

                                <div class="space-y-2">
                                    @php
                                        $dayTasks = $this->tasks()->filter(function($task) use ($day) {
                                            return $task->start_date <= $day && $task->end_date >= $day;
                                        });
                                    @endphp

                                    @forelse($dayTasks as $task)
                                        <div
                                            wire:key="timeline-task-{{ $task->id }}"
                                            class="cursor-pointer rounded border-l-4 bg-zinc-50 p-2 hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-750"
                                            style="border-left-color: {{ match($task->priority) {
                                                'urgent' => '#ef4444',
                                                'high' => '#f59e0b',
                                                'medium' => '#3b82f6',
                                                'low' => '#6b7280',
                                                default => '#6b7280'
                                            } }}"
                                            wire:click="editTask({{ $task->id }})"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ $task->title }}
                                                    </div>
                                                    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                        <span>{{ $task->duration }} min</span>
                                                        <span>•</span>
                                                        <span>{{ $this->getStatusLabel($task->status) }}</span>
                                                        @if($task->project)
                                                            <span>•</span>
                                                            <span>{{ $task->project->name }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <flux:badge :variant="$this->getPriorityVariant($task->priority)" size="sm">
                                                    {{ ucfirst($task->priority) }}
                                                </flux:badge>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="py-2 text-center text-xs text-zinc-400 dark:text-zinc-500">
                                            No tasks scheduled
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </se>

    {{-- Task Modal --}}
    <flux:modal wire:model="showTaskModal" class="w-full max-w-2xl">
        <form wire:submit="saveTask" class="space-y-4">
            <flux:heading size="lg">{{ $editingTaskId ? 'Edit Task' : 'Create Task' }}</flux:heading>

            <flux:input
                wire:model="taskTitle"
                label="Title"
                placeholder="Enter task title"
                required
            />

            <flux:textarea
                wire:model="taskDescription"
                label="Description"
                placeholder="Enter task description"
                rows="3"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="taskStatus" label="Status" required>
                    <option value="to_do">To Do</option>
                    <option value="doing">In Progress</option>
                    <option value="done">Done</option>
                </flux:select>

                <flux:select wire:model="taskPriority" label="Priority" required>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </flux:select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="taskComplexity" label="Complexity" required>
                    <option value="simple">Simple</option>
                    <option value="moderate">Moderate</option>
                    <option value="complex">Complex</option>
                </flux:select>

                <flux:input
                    wire:model="taskDuration"
                    label="Duration (minutes)"
                    type="number"
                    min="1"
                    required
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="taskStartDate"
                    label="Start Date"
                    type="date"
                    required
                />

                <flux:input
                    wire:model="taskEndDate"
                    label="End Date"
                    type="date"
                    required
                />
            </div>

            <flux:select wire:model="taskProjectId" label="Project" placeholder="Select a project">
                <option value="">No Project</option>
                @foreach($this->projects() as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </flux:select>

            <div>
                <flux:field>
                    <flux:label>Tags</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($this->allTags() as $tag)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3 py-1.5 text-sm dark:border-zinc-700 {{ in_array($tag->id, $selectedTags) ? 'bg-blue-50 border-blue-300 dark:bg-blue-900/20 dark:border-blue-600' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                <input
                                    type="checkbox"
                                    wire:model="selectedTags"
                                    value="{{ $tag->id }}"
                                    class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800"
                                />
                                <span>{{ $tag->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="$set('showTaskModal', false)" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingTaskId ? 'Update Task' : 'Create Task' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>

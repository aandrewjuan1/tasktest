<?php

namespace App\Traits;

use App\Enums\CollaborationPermission;
use App\Enums\EventStatus;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Collaboration;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CollaborationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

trait HandlesWorkspaceItems
{
    public function deleteTask(int|string $taskId): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::findOrFail($baseTaskId);
            $this->authorize('delete', $task);

            $this->getTaskService()->deleteTask($task);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Task deleted successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete task from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete task. Please try again.', type: 'error');
        }
    }

    public function addTaskComment(int|string $taskId, string $content): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::findOrFail($baseTaskId);

            $user = Auth::user();

            if (! $task->canUserComment($user)) {
                $this->authorize('update', $task); // Will throw authorization exception
            }

            validator(
                ['content' => $content],
                ['content' => ['required', 'string', 'max:2000']],
                [],
                ['content' => 'comment'],
            )->validate();

            Comment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'content' => $content,
                'is_edited' => false,
            ]);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Comment added', type: 'success');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to add task comment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to add comment. Please try again.', type: 'error');
        }
    }

    public function updateTaskComment(int|string $taskId, int $commentId, string $content): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::findOrFail($baseTaskId);
            $comment = Comment::where('task_id', $task->id)->findOrFail($commentId);

            $user = Auth::user();

            $isOwner = $task->user_id === $user->id;
            $isAuthor = $comment->user_id === $user->id;

            if (! $isOwner && ! $isAuthor) {
                $this->authorize('update', $task); // Fallback to policy
            }

            validator(
                ['content' => $content],
                ['content' => ['required', 'string', 'max:2000']],
                [],
                ['content' => 'comment'],
            )->validate();

            $comment->update([
                'content' => $content,
                'is_edited' => true,
                'edited_at' => Carbon::now(),
            ]);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Comment updated', type: 'success');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update task comment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'comment_id' => $commentId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update comment. Please try again.', type: 'error');
        }
    }

    public function deleteTaskComment(int|string $taskId, int $commentId): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::findOrFail($baseTaskId);
            $comment = Comment::where('task_id', $task->id)->findOrFail($commentId);

            $user = Auth::user();

            $isOwner = $task->user_id === $user->id;
            $isAuthor = $comment->user_id === $user->id;

            if (! $isOwner && ! $isAuthor) {
                $this->authorize('delete', $task); // Fallback to policy
            }

            $comment->delete();

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Comment deleted', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete task comment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'comment_id' => $commentId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete comment. Please try again.', type: 'error');
        }
    }

    public function addTaskCollaborator(int|string $taskId, string $email, string $permission): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::findOrFail($baseTaskId);
            $this->authorize('update', $task);

            // Only task owner can manage collaborators
            if ($task->user_id !== Auth::id()) {
                $this->dispatch('notify', message: 'Only the task owner can manage collaborators.', type: 'error');

                return;
            }

            validator(
                ['email' => $email, 'permission' => $permission],
                [
                    'email' => ['required', 'email', 'exists:users,email'],
                    'permission' => ['required', 'string', Rule::in(['view', 'edit'])],
                ],
                [],
                ['email' => 'email', 'permission' => 'permission'],
            )->validate();

            $user = User::where('email', $email)->firstOrFail();

            // Check if user is the task owner
            if ($task->user_id === $user->id) {
                $this->dispatch('notify', message: 'Cannot add task owner as a collaborator.', type: 'error');

                return;
            }

            // Check if user is already a collaborator
            if ($task->isCollaborator($user)) {
                $this->dispatch('notify', message: 'User is already a collaborator on this task.', type: 'error');

                return;
            }

            $permissionEnum = CollaborationPermission::from($permission);
            $collaborationService = app(CollaborationService::class);
            $collaborationService->addCollaborator($task, $user, $permissionEnum);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Collaborator added successfully', type: 'success');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Extract email validation error message
            $errors = $e->errors();
            $emailError = $errors['email'][0] ?? 'The email address is invalid or does not exist.';

            // Dispatch event for frontend to catch (don't throw, let frontend handle it)
            $this->dispatch('collaborator-validation-error', message: $emailError, email: $email);

            // Don't throw - let the frontend handle the error display
            return;
        } catch (\Exception $e) {
            Log::error('Failed to add task collaborator', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'email' => $email,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to add collaborator. Please try again.', type: 'error');
        }
    }

    public function updateTaskCollaboratorPermission(int|string $taskId, int $collaborationId, string $permission): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::findOrFail($baseTaskId);
            $this->authorize('update', $task);

            // Only task owner can manage collaborators
            if ($task->user_id !== Auth::id()) {
                $this->dispatch('notify', message: 'Only the task owner can manage collaborators.', type: 'error');

                return;
            }

            $collaboration = Collaboration::where('collaboratable_type', Task::class)
                ->where('collaboratable_id', $task->id)
                ->findOrFail($collaborationId);

            validator(
                ['permission' => $permission],
                ['permission' => ['required', 'string', Rule::in(['view', 'edit'])],
                ],
                [],
                ['permission' => 'permission'],
            )->validate();

            $permissionEnum = CollaborationPermission::from($permission);
            $collaborationService = app(CollaborationService::class);
            $collaborationService->updateCollaboratorPermission($collaboration, $permissionEnum);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Collaborator permission updated', type: 'success');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update task collaborator permission', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'collaboration_id' => $collaborationId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update collaborator permission. Please try again.', type: 'error');
        }
    }

    public function removeTaskCollaborator(int|string $taskId, int $collaborationId): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::findOrFail($baseTaskId);
            $this->authorize('update', $task);

            // Only task owner can manage collaborators
            if ($task->user_id !== Auth::id()) {
                $this->dispatch('notify', message: 'Only the task owner can manage collaborators.', type: 'error');

                return;
            }

            $collaboration = Collaboration::where('collaboratable_type', Task::class)
                ->where('collaboratable_id', $task->id)
                ->findOrFail($collaborationId);

            $collaborationService = app(CollaborationService::class);
            $collaborationService->removeCollaborator($collaboration);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Collaborator removed successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to remove task collaborator', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'collaboration_id' => $collaborationId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to remove collaborator. Please try again.', type: 'error');
        }
    }

    public function createTask(array $data): void
    {
        $this->authorize('create', Task::class);

        try {
            $validated = validator($data, [
                'title' => ['required', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'in:to_do,doing,done'],
                'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
                'complexity' => ['nullable', 'string', 'in:simple,moderate,complex'],
                'duration' => ['nullable', 'integer', 'min:1'],
                'startDatetime' => ['nullable', 'date'],
                'endDatetime' => ['nullable', 'date', 'after_or_equal:startDatetime'],
                'projectId' => ['nullable', 'exists:projects,id'],
                'tagIds' => ['nullable', 'array'],
                'tagIds.*' => ['integer', 'exists:tags,id'],
                'recurrence' => ['nullable', 'array'],
                'recurrence.enabled' => ['nullable', 'boolean'],
                'recurrence.type' => ['nullable', 'required_if:recurrence.enabled,true', 'string', 'in:daily,weekly,monthly,yearly'],
                'recurrence.interval' => ['nullable', 'required_if:recurrence.enabled,true', 'integer', 'min:1'],
                'recurrence.daysOfWeek' => ['nullable', 'array'],
                'recurrence.daysOfWeek.*' => ['integer', 'min:0', 'max:6'],
            ], [], [
                'title' => 'title',
                'status' => 'status',
                'priority' => 'priority',
                'complexity' => 'complexity',
                'duration' => 'duration',
                'startDatetime' => 'start datetime',
                'endDatetime' => 'end datetime',
                'projectId' => 'project',
                'recurrence.type' => 'recurrence type',
                'recurrence.interval' => 'recurrence interval',
            ])->validate();

            $this->getTaskService()->createTask($validated, Auth::id());

            $this->dispatch('notify', message: 'Task created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Task creation validation failed', [
                'user_id' => Auth::id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create task', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'input_data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to create task. Please try again.', type: 'error');
        }
    }

    public function updateTaskField(int|string $taskId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        try {
            $baseTaskId = is_string($taskId) && str_contains($taskId, '-')
                ? (int) explode('-', $taskId)[0]
                : (int) $taskId;

            $task = Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                ->findOrFail($baseTaskId);

            if (! $instanceDate && is_string($taskId) && str_contains($taskId, '-')) {
                $parts = explode('-', $taskId, 2);
                if (count($parts) === 2) {
                    $instanceDate = $parts[1];
                }
            }

            $this->authorize('update', $task);

            $validationRules = $this->taskFieldRules($field);

            if (empty($validationRules)) {
                return;
            }

            if ($field === 'recurrence') {
                if ($value === null) {
                    // Disabling recurrence, no validation needed.
                } else {
                    $rules = [
                        'recurrence' => ['nullable', 'array'],
                        'recurrence.enabled' => ['nullable', 'boolean'],
                        'recurrence.type' => ['nullable', 'required_if:recurrence.enabled,true', 'string', 'in:daily,weekly,monthly,yearly'],
                        'recurrence.interval' => ['nullable', 'required_if:recurrence.enabled,true', 'integer', 'min:1'],
                        'recurrence.daysOfWeek' => ['nullable', 'array'],
                        'recurrence.daysOfWeek.*' => ['integer', 'min:0', 'max:6'],
                        'recurrence.startDatetime' => ['nullable', 'date'],
                        'recurrence.endDatetime' => ['nullable', 'date', 'after_or_equal:recurrence.startDatetime'],
                    ];
                    validator(
                        [$field => $value],
                        $rules,
                        [],
                        [
                            'recurrence.type' => 'recurrence type',
                            'recurrence.interval' => 'recurrence interval',
                        ],
                    )->validate();
                }
            } else {
                validator(
                    [$field => $value],
                    [$field => $validationRules],
                    [],
                    [$field => $field],
                )->validate();
            }

            $this->getTaskService()->updateTaskField($task, $field, $value, $instanceDate);

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update task field from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'task_id' => $taskId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update task. Please try again.', type: 'error');
        }
    }

    public function updateTaskTags(int $itemId, string $itemType, array $tagIds): void
    {
        try {
            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($itemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($itemId),
                'project' => Project::with(['tags', 'tasks', 'reminders'])
                    ->findOrFail($itemId),
                default => throw new \InvalidArgumentException("Invalid item type: {$itemType}"),
            };

            $this->authorize('update', $model);

            validator(
                ['tagIds' => $tagIds],
                [
                    'tagIds' => ['nullable', 'array'],
                    'tagIds.*' => ['integer', 'exists:tags,id'],
                ],
                [],
                ['tagIds' => 'tags'],
            )->validate();

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskTags($model, $tagIds),
                'event' => $this->getEventService()->updateEventTags($model, $tagIds),
                'project' => $this->getProjectService()->updateProjectTags($model, $tagIds),
            };

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update tags from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update tags. Please try again.', type: 'error');
        }
    }

    public function updateEventField(int|string $eventId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        try {
            $baseEventId = is_string($eventId) && str_contains($eventId, '-')
                ? (int) explode('-', $eventId)[0]
                : (int) $eventId;

            $event = Event::with(['tags', 'reminders', 'recurringEvent'])
                ->findOrFail($baseEventId);

            if (! $instanceDate && is_string($eventId) && str_contains($eventId, '-')) {
                $parts = explode('-', $eventId, 2);
                if (count($parts) === 2) {
                    $instanceDate = $parts[1];
                }
            }

            $this->authorize('update', $event);

            $validationRules = $this->eventFieldRules($field);

            if (empty($validationRules)) {
                return;
            }

            validator(
                [$field => $value],
                [$field => $validationRules],
                [],
                [$field => $field],
            )->validate();

            $this->getEventService()->updateEventField($event, $field, $value, $instanceDate);

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update event field from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'event_id' => $eventId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update event. Please try again.', type: 'error');
        }
    }

    public function deleteEvent(int|string $eventId): void
    {
        try {
            $baseEventId = is_string($eventId) && str_contains($eventId, '-')
                ? (int) explode('-', $eventId)[0]
                : (int) $eventId;

            $event = Event::findOrFail($baseEventId);
            $this->authorize('delete', $event);

            $this->getEventService()->deleteEvent($event);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Event deleted successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete event from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event_id' => $eventId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete event. Please try again.', type: 'error');
        }
    }

    public function updateProjectField(int $projectId, string $field, mixed $value): void
    {
        try {
            $project = Project::with(['tags', 'tasks', 'reminders'])
                ->findOrFail($projectId);

            $this->authorize('update', $project);

            $validationRules = $this->projectFieldRules($field);

            if (empty($validationRules)) {
                return;
            }

            validator(
                [$field => $value],
                [$field => $validationRules],
                [],
                [$field => $field],
            )->validate();

            $this->getProjectService()->updateProjectField($project, $field, $value);

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update project field from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'project_id' => $projectId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update project. Please try again.', type: 'error');
        }
    }

    public function deleteProject(int $projectId): void
    {
        try {
            $project = Project::findOrFail($projectId);
            $this->authorize('delete', $project);

            $this->getProjectService()->deleteProject($project);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Project deleted successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete project from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete project. Please try again.', type: 'error');
        }
    }

    public function createEvent(array $data): void
    {
        $this->authorize('create', Event::class);

        try {
            $validated = validator($data, [
                'title' => ['required', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'in:scheduled,cancelled,completed,tentative,ongoing'],
                'startDatetime' => ['nullable', 'date'],
                'endDatetime' => ['nullable', 'date', 'after:startDatetime'],
                'tagIds' => ['nullable', 'array'],
                'tagIds.*' => ['integer', 'exists:tags,id'],
                'recurrence' => ['nullable', 'array'],
                'recurrence.enabled' => ['nullable', 'boolean'],
                'recurrence.type' => ['nullable', 'required_if:recurrence.enabled,true', 'string', 'in:daily,weekly,monthly,yearly'],
                'recurrence.interval' => ['nullable', 'required_if:recurrence.enabled,true', 'integer', 'min:1'],
                'recurrence.daysOfWeek' => ['nullable', 'array'],
                'recurrence.daysOfWeek.*' => ['integer', 'min:0', 'max:6'],
            ], [], [
                'title' => 'title',
                'status' => 'status',
                'startDatetime' => 'start datetime',
                'endDatetime' => 'end datetime',
                'recurrence.type' => 'recurrence type',
                'recurrence.interval' => 'recurrence interval',
            ])->validate();

            $this->getEventService()->createEvent($validated, Auth::id());

            $this->dispatch('notify', message: 'Event created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Event creation validation failed', [
                'user_id' => Auth::id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create event', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'input_data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to create event. Please try again.', type: 'error');
        }
    }

    public function createProject(array $data): void
    {
        $this->authorize('create', Project::class);

        try {
            $validated = validator($data, [
                'name' => ['required', 'string', 'max:255'],
                'startDatetime' => ['nullable', 'date'],
                'endDatetime' => ['nullable', 'date', 'after_or_equal:startDatetime'],
                'tagIds' => ['nullable', 'array'],
                'tagIds.*' => ['integer', 'exists:tags,id'],
            ], [], [
                'name' => 'name',
                'startDatetime' => 'start datetime',
                'endDatetime' => 'end datetime',
            ])->validate();

            $this->getProjectService()->createProject($validated, Auth::id());

            $this->dispatch('notify', message: 'Project created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Project creation validation failed', [
                'user_id' => Auth::id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create project', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'input_data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to create project. Please try again.', type: 'error');
        }
    }

    public function updateItemDateTime(int|string $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        try {
            $baseItemId = is_string($itemId) && str_contains($itemId, '-')
                ? (int) explode('-', $itemId)[0]
                : (int) $itemId;

            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($baseItemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($baseItemId),
                'project' => Project::with(['tags', 'tasks', 'reminders'])
                    ->findOrFail($baseItemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskDateTime($model, $newStart, $newEnd),
                'event' => $this->getEventService()->updateEventDateTime($model, $newStart, $newEnd),
                'project' => $this->getProjectService()->updateProjectDateTime($model, $newStart, $newEnd),
            };

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Item updated successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to update item datetime', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to update item. Please try again.', type: 'error');
        }
    }

    public function updateItemDuration(int|string $itemId, string $itemType, int $newDurationMinutes): void
    {
        try {
            $baseItemId = is_string($itemId) && str_contains($itemId, '-')
                ? (int) explode('-', $itemId)[0]
                : (int) $itemId;

            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($baseItemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($baseItemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskDuration($model, $newDurationMinutes),
                'event' => $this->getEventService()->updateEventDuration($model, $newDurationMinutes),
            };

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Duration updated successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to update item duration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to update duration. Please try again.', type: 'error');
        }
    }

    public function updateItemStatus(int|string $itemId, string $itemType, string $newStatus, ?string $instanceDate = null): void
    {
        try {
            $baseItemId = is_string($itemId) && str_contains($itemId, '-')
                ? (int) explode('-', $itemId)[0]
                : (int) $itemId;

            if (! $instanceDate && is_string($itemId) && str_contains($itemId, '-')) {
                $parts = explode('-', $itemId, 2);
                if (count($parts) === 2) {
                    $instanceDate = $parts[1];
                }
            }

            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($baseItemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($baseItemId),
                'project' => Project::with(['tags', 'tasks', 'reminders'])
                    ->findOrFail($baseItemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskStatus($model, $newStatus, $instanceDate),
                'event' => $this->getEventService()->updateEventStatus($model, $newStatus, $instanceDate),
                'project' => null,
            };

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Status updated successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to update item status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to update status. Please try again.', type: 'error');
        }
    }

    protected function taskFieldRules(string $field): array
    {
        $statusValues = array_map(fn ($c) => $c->value, TaskStatus::cases());
        $priorityValues = array_map(fn ($c) => $c->value, TaskPriority::cases());
        $complexityValues = array_map(fn ($c) => $c->value, TaskComplexity::cases());

        return match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            'priority' => ['nullable', 'string', Rule::in($priorityValues)],
            'complexity' => ['nullable', 'string', Rule::in($complexityValues)],
            'duration' => ['nullable', 'integer', 'min:1'],
            'startDatetime' => ['nullable', 'date'],
            'endDatetime' => ['nullable', 'date'],
            'projectId' => ['nullable', 'exists:projects,id'],
            'recurrence' => ['nullable', 'array'],
            default => [],
        };
    }

    protected function eventFieldRules(string $field): array
    {
        $statusValues = array_map(fn ($c) => $c->value, EventStatus::cases());

        return match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startDatetime' => ['nullable', 'date'],
            'endDatetime' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            default => [],
        };
    }

    protected function projectFieldRules(string $field): array
    {
        return match ($field) {
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startDatetime' => ['nullable', 'date'],
            'endDatetime' => ['nullable', 'date', 'after_or_equal:startDatetime'],
            default => [],
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getDateRangeForView(): array
    {
        return $this->getDateRangeForViewTrait($this->viewMode, $this->currentDate, $this->weekStartDate);
    }

    protected function filterInstances(Collection $instances): Collection
    {
        return $instances->filter(function ($instance): bool {
            if ($this->filterStatus && $this->filterStatus !== 'all') {
                if ($instance->status?->value !== $this->filterStatus) {
                    return false;
                }
            }

            if ($this->filterPriority && $this->filterPriority !== 'all') {
                if (isset($instance->priority) && $instance->priority?->value !== $this->filterPriority) {
                    return false;
                }
            }

            if (! empty($this->filterTagIds)) {
                $instanceTagIds = $instance->tags->pluck('id')->toArray();
                if (empty(array_intersect($this->filterTagIds, $instanceTagIds))) {
                    return false;
                }
            }

            if (isset($instance->cancelled) && $instance->cancelled) {
                return false;
            }

            if (in_array($this->viewMode, ['daily-timegrid', 'weekly-timegrid'], true)) {
                return $instance->start_datetime !== null || $instance->end_datetime !== null;
            }

            return true;
        });
    }

    protected function applyBaseFilters($query, string $itemType)
    {
        $shouldFilter = match ($itemType) {
            'task' => $this->filterType === 'task' || ! $this->filterType || $this->filterType === 'all',
            'event' => $this->filterType === 'event' || ! $this->filterType || $this->filterType === 'all',
            default => false,
        };

        if (! $shouldFilter) {
            return $query;
        }

        if ($this->filterPriority && $this->filterPriority !== 'all') {
            $query->filterByPriority($this->filterPriority);
        }

        if ($this->filterStatus && $this->filterStatus !== 'all') {
            $query->filterByStatus($this->filterStatus);
        }

        if (! empty($this->filterTagIds)) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->filterTagIds));
        }

        return $query;
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return ($this->filterType && $this->filterType !== 'all')
            || ($this->filterPriority && $this->filterPriority !== 'all')
            || ($this->filterStatus && $this->filterStatus !== 'all')
            || ! empty($this->filterTagIds);
    }

    #[Computed]
    public function filteredTasks(): Collection
    {
        $user = Auth::user();
        $dateRange = $this->getDateRangeForView();

        $query = Task::query()
            ->accessibleBy($user)
            ->with(['project.tasks', 'tags', 'event', 'recurringTask', 'collaborations.user', 'user']);

        $this->applyBaseFilters($query, 'task');

        if ($this->filterType && $this->filterType !== 'all' && $this->filterType !== 'task') {
            return collect();
        }

        $tasks = $query->get();

        $allInstances = collect();
        foreach ($tasks as $task) {
            $instances = $task->getInstancesForDateRange($dateRange['start'], $dateRange['end']);
            $allInstances = $allInstances->merge($instances);
        }

        $filteredInstances = $this->filterInstances($allInstances);

        return $this->sortCollection($filteredInstances, $this->sortBy, $this->sortDirection)
            ->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_datetime ?? $task->start_datetime ?? $task->created_at;

                return $task;
            });
    }

    #[Computed]
    public function filteredEvents(): Collection
    {
        $user = Auth::user();
        $dateRange = $this->getDateRangeForView();

        $query = Event::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks', 'recurringEvent']);

        $this->applyBaseFilters($query, 'event');

        if ($this->filterType && $this->filterType !== 'all' && $this->filterType !== 'event') {
            return collect();
        }

        $events = $query->get();

        $allInstances = collect();
        foreach ($events as $event) {
            $instances = $event->getInstancesForDateRange($dateRange['start'], $dateRange['end']);
            $allInstances = $allInstances->merge($instances);
        }

        $filteredInstances = $this->filterInstances($allInstances);

        return $this->sortCollection($filteredInstances, $this->sortBy, $this->sortDirection)
            ->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime ?? $event->created_at;

                return $event;
            });
    }

    #[Computed]
    public function filteredItems(): Collection
    {
        return collect()
            ->merge($this->filteredTasks)
            ->merge($this->filteredEvents);
    }

    #[Computed]
    public function itemsByStatus(): array
    {
        $items = $this->filteredItems;

        return [
            'to_do' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['to_do', 'scheduled', 'tentative'])),
            'doing' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['doing', 'ongoing'])),
            'done' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['done', 'completed'])),
        ];
    }
}

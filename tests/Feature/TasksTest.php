<?php

declare(strict_types=1);

use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected to the login page', function () {
    $this->get('/tasks')->assertRedirect('/login');
});

test('authenticated users can visit the tasks page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/tasks')->assertOk();
});

test('tasks page displays only user tasks', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userTask = Task::factory()->for($user)->create(['title' => 'User Task']);
    $otherTask = Task::factory()->for($otherUser)->create(['title' => 'Other User Task']);

    $this->actingAs($user);

    Volt::test('tasks.index')
        ->assertSee('User Task')
        ->assertDontSee('Other User Task');
});

test('user can create a new task', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->set('taskTitle', 'New Test Task')
        ->set('taskDescription', 'This is a test task description')
        ->set('taskStatus', 'to_do')
        ->set('taskPriority', 'high')
        ->set('taskComplexity', 'simple')
        ->set('taskDuration', 60)
        ->set('taskStartDate', now()->format('Y-m-d'))
        ->set('taskEndDate', now()->addDay()->format('Y-m-d'))
        ->call('saveTask');

    $response->assertHasNoErrors();

    expect(Task::where('title', 'New Test Task')->exists())->toBeTrue();
    expect(Task::where('title', 'New Test Task')->first()->user_id)->toBe($user->id);
});

test('user can update an existing task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['title' => 'Original Title']);

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->call('editTask', $task->id)
        ->set('taskTitle', 'Updated Title')
        ->call('saveTask');

    $response->assertHasNoErrors();

    expect($task->fresh()->title)->toBe('Updated Title');
});

test('user cannot update another user task', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $task = Task::factory()->for($otherUser)->create(['title' => 'Other User Task']);

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->call('editTask', $task->id)
        ->set('taskTitle', 'Hacked Title')
        ->call('saveTask');

    expect($task->fresh()->title)->toBe('Other User Task');
});

test('user cannot delete another user task', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $task = Task::factory()->for($otherUser)->create();

    $this->actingAs($user);

    Volt::test('tasks.index')
        ->call('deleteTask', $task->id);

    expect(Task::find($task->id))->not->toBeNull();
    expect(Task::withTrashed()->find($task->id)->trashed())->toBeFalse();
});

test('user can toggle task completion', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['status' => 'to_do']);

    $this->actingAs($user);

    Volt::test('tasks.index')
        ->call('toggleComplete', $task->id);

    expect($task->fresh()->status)->toBe('done');
    expect($task->fresh()->completed_at)->not->toBeNull();
});

test('user can switch between views', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('tasks.index');

    expect($component->get('view'))->toBe('list');

    $component->call('switchView', 'kanban');
    expect($component->get('view'))->toBe('kanban');

    $component->call('switchView', 'timeline');
    expect($component->get('view'))->toBe('timeline');
});

test('search filters tasks correctly', function () {
    $user = User::factory()->create();

    Task::factory()->for($user)->create(['title' => 'Search This Task']);
    Task::factory()->for($user)->create(['title' => 'Another Task']);

    $this->actingAs($user);

    $component = Volt::test('tasks.index')
        ->set('search', 'Search This');

    $tasks = $component->get('tasks');

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->title)->toBe('Search This Task');
});

test('status filter works correctly', function () {
    $user = User::factory()->create();

    Task::factory()->for($user)->create(['status' => 'to_do']);
    Task::factory()->for($user)->create(['status' => 'doing']);
    Task::factory()->for($user)->create(['status' => 'done']);

    $this->actingAs($user);

    $component = Volt::test('tasks.index')
        ->set('statusFilter', 'doing');

    $tasks = $component->get('tasks');

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->status)->toBe('doing');
});

test('priority filter works correctly', function () {
    $user = User::factory()->create();

    Task::factory()->for($user)->create(['priority' => 'low']);
    Task::factory()->for($user)->create(['priority' => 'high']);
    Task::factory()->for($user)->urgent()->create();

    $this->actingAs($user);

    $component = Volt::test('tasks.index')
        ->set('priorityFilter', 'urgent');

    $tasks = $component->get('tasks');

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->priority)->toBe('urgent');
});

test('tag filter works correctly', function () {
    $user = User::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'work']);
    $tag2 = Tag::factory()->create(['name' => 'personal']);

    $task1 = Task::factory()->for($user)->create();
    $task2 = Task::factory()->for($user)->create();

    $task1->tags()->attach($tag1);
    $task2->tags()->attach($tag2);

    $this->actingAs($user);

    $component = Volt::test('tasks.index')
        ->set('tagFilter', $tag1->id);

    $tasks = $component->get('tasks');

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->id)->toBe($task1->id);
});

test('user can create task with tags', function () {
    $user = User::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'urgent']);
    $tag2 = Tag::factory()->create(['name' => 'work']);

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->set('taskTitle', 'Task with Tags')
        ->set('taskDescription', 'Description')
        ->set('taskStatus', 'to_do')
        ->set('taskPriority', 'medium')
        ->set('taskComplexity', 'simple')
        ->set('taskDuration', 30)
        ->set('taskStartDate', now()->format('Y-m-d'))
        ->set('taskEndDate', now()->addDay()->format('Y-m-d'))
        ->set('selectedTags', [$tag1->id, $tag2->id])
        ->call('saveTask');

    $response->assertHasNoErrors();

    $task = Task::where('title', 'Task with Tags')->first();
    expect($task->tags)->toHaveCount(2);
    expect($task->tags->pluck('id')->toArray())->toContain($tag1->id, $tag2->id);
});

test('user can create task with project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->set('taskTitle', 'Task with Project')
        ->set('taskDescription', 'Description')
        ->set('taskStatus', 'to_do')
        ->set('taskPriority', 'medium')
        ->set('taskComplexity', 'simple')
        ->set('taskDuration', 30)
        ->set('taskStartDate', now()->format('Y-m-d'))
        ->set('taskEndDate', now()->addDay()->format('Y-m-d'))
        ->set('taskProjectId', $project->id)
        ->call('saveTask');

    $response->assertHasNoErrors();

    $task = Task::where('title', 'Task with Project')->first();
    expect($task->project_id)->toBe($project->id);
});

test('task validation requires title', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->set('taskTitle', '')
        ->set('taskStatus', 'to_do')
        ->set('taskPriority', 'medium')
        ->set('taskComplexity', 'simple')
        ->set('taskDuration', 30)
        ->set('taskStartDate', now()->format('Y-m-d'))
        ->set('taskEndDate', now()->addDay()->format('Y-m-d'))
        ->call('saveTask');

    $response->assertHasErrors(['taskTitle']);
});

test('task validation requires valid status', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->set('taskTitle', 'Test Task')
        ->set('taskStatus', 'invalid_status')
        ->set('taskPriority', 'medium')
        ->set('taskComplexity', 'simple')
        ->set('taskDuration', 30)
        ->set('taskStartDate', now()->format('Y-m-d'))
        ->set('taskEndDate', now()->addDay()->format('Y-m-d'))
        ->call('saveTask');

    $response->assertHasErrors(['taskStatus']);
});

test('task validation requires end date after start date', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->set('taskTitle', 'Test Task')
        ->set('taskStatus', 'to_do')
        ->set('taskPriority', 'medium')
        ->set('taskComplexity', 'simple')
        ->set('taskDuration', 30)
        ->set('taskStartDate', now()->format('Y-m-d'))
        ->set('taskEndDate', now()->subDay()->format('Y-m-d'))
        ->call('saveTask');

    $response->assertHasErrors(['taskEndDate']);
});

test('inline editing updates task title', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['title' => 'Original Title']);

    $this->actingAs($user);

    Volt::test('tasks.index')
        ->call('startInlineEdit', $task->id, 'Original Title')
        ->set('inlineTitle', 'Inline Updated Title')
        ->call('saveInlineEdit');

    expect($task->fresh()->title)->toBe('Inline Updated Title');
});

test('update task status in kanban view', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['status' => 'to_do']);

    $this->actingAs($user);

    Volt::test('tasks.index')
        ->call('updateTaskStatus', $task->id, 'doing');

    expect($task->fresh()->status)->toBe('doing');
});

test('user can restore a soft deleted task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['title' => 'Restorable Task']);

    $task->delete();

    expect(Task::find($task->id))->toBeNull();
    expect(Task::withTrashed()->find($task->id)->trashed())->toBeTrue();

    $task->restore();

    expect(Task::find($task->id))->not->toBeNull();
    expect(Task::find($task->id)->trashed())->toBeFalse();
});

test('user can force delete a task permanently', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['title' => 'Permanently Deleted Task']);

    $taskId = $task->id;
    $task->forceDelete();

    expect(Task::find($taskId))->toBeNull();
    expect(Task::withTrashed()->find($taskId))->toBeNull();
});

test('soft deleted tasks can be queried with withTrashed', function () {
    $user = User::factory()->create();

    $activeTask = Task::factory()->for($user)->create(['title' => 'Active']);
    $deletedTask = Task::factory()->for($user)->create(['title' => 'Deleted']);
    $deletedTask->delete();

    $allTasks = Task::withTrashed()->where('user_id', $user->id)->get();
    $onlyDeleted = Task::onlyTrashed()->where('user_id', $user->id)->get();

    expect($allTasks)->toHaveCount(2);
    expect($onlyDeleted)->toHaveCount(1);
    expect($onlyDeleted->first()->title)->toBe('Deleted');
});

test('user can create task with only title', function () {
    $user = User::factory()->create();

    $task = Task::create([
        'user_id' => $user->id,
        'title' => 'Quick Task',
    ]);

    expect($task->status)->toBe(TaskStatus::ToDo);
    expect($task->priority)->toBe(TaskPriority::Medium);
    expect($task->complexity)->toBe(TaskComplexity::Moderate);
    expect($task->start_date)->toBeNull();
    expect($task->end_date)->toBeNull();
});

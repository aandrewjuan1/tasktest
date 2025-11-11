<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

test('guests cannot access the tasks page', function () {
    $this->get('/tasks')->assertRedirect('/login');
});

test('authenticated users can view their tasks', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/tasks')->assertOk();
});

test('users can create a task from the tasks page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->set('form.title', 'Linear Algebra Homework')
        ->set('form.subject', 'Mathematics')
        ->set('form.type', 'assignment')
        ->set('form.priority', 'high')
        ->set('form.status', 'to-do')
        ->set('form.deadline', Carbon::now()->addDay()->format('Y-m-d\TH:i'))
        ->set('form.estimated_minutes', 90)
        ->set('form.description', 'Complete problem set 5')
        ->call('createTask');

    $response->assertHasNoErrors();
    $response->assertDispatched('task-created');

    $task = $user->tasks()->firstOrFail();

    expect($task->title)->toBe('Linear Algebra Homework');
    expect($task->priority)->toBe('high');
    expect($task->status)->toBe('to-do');
    expect($task->estimated_minutes)->toBe(90);
});

test('users can update task status and delete tasks', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => 'to-do',
    ]);

    $this->actingAs($user);

    $response = Volt::test('tasks.index')
        ->call('updateStatus', $task->getKey(), 'completed');

    $response->assertDispatched('task-updated');

    $task->refresh();

    expect($task->status)->toBe('completed');
    expect($task->completed_at)->not->toBeNull();

    $response = Volt::test('tasks.index')
        ->call('deleteTask', $task->getKey());

    $response->assertDispatched('task-deleted');

    expect(Task::whereKey($task->getKey())->exists())->toBeFalse();
});

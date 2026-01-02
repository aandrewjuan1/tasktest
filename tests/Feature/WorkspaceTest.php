<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('workspace page loads for authenticated user', function () {
    actingAs($this->user)
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertSeeLivewire('workspace.index')
        ->assertSeeLivewire('workspace.show-items')
        ->assertSeeLivewire('workspace.calendar-view')
        ->assertSeeLivewire('workspace.create-item-modal');
});

test('workspace page requires authentication', function () {
    get(route('workspace.index'))
        ->assertRedirect(route('login'));
});

test('workspace displays user tasks', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Test Task',
        'status' => 'to_do',
    ]);

    actingAs($this->user)
        ->get(route('workspace.index'))
        ->assertSee('Test Task');
});

test('workspace displays user events', function () {
    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Test Event',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
    ]);

    actingAs($this->user)
        ->get(route('workspace.index'))
        ->assertSee('Test Event');
});

test('workspace displays user projects', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    actingAs($this->user)
        ->get(route('workspace.index'))
        ->assertSee('Test Project');
});

test('workspace does not display other users tasks', function () {
    $otherUser = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $otherUser->id,
        'title' => 'Other User Task',
    ]);

    actingAs($this->user)
        ->get(route('workspace.index'))
        ->assertDontSee('Other User Task');
});

test('can create task through modal', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'task')
        ->set('taskTitle', 'New Task')
        ->set('taskDescription', 'Task description')
        ->set('taskStatus', 'to_do')
        ->call('createTask')
        ->assertDispatched('task-created');

    expect(Task::where('title', 'New Task')->exists())->toBeTrue();
});

test('task creation requires title', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'task')
        ->set('taskTitle', '')
        ->set('taskStatus', 'to_do')
        ->call('createTask')
        ->assertHasErrors(['taskTitle']);
});

test('can create event through modal', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'event')
        ->set('eventTitle', 'New Event')
        ->set('eventStartDatetime', now()->addDay()->format('Y-m-d H:i'))
        ->set('eventEndDatetime', now()->addDay()->addHour()->format('Y-m-d H:i'))
        ->call('createEvent')
        ->assertDispatched('event-created');

    expect(Event::where('title', 'New Event')->exists())->toBeTrue();
});

test('event creation requires title and datetime', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'event')
        ->set('eventTitle', '')
        ->call('createEvent')
        ->assertHasErrors(['eventTitle', 'eventStartDatetime', 'eventEndDatetime']);
});

test('event end datetime must be after start datetime', function () {
    actingAs($this->user);

    $start = now()->addDay();
    $end = now()->addHour();

    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'event')
        ->set('eventTitle', 'Test Event')
        ->set('eventStartDatetime', $start->format('Y-m-d H:i'))
        ->set('eventEndDatetime', $end->format('Y-m-d H:i'))
        ->call('createEvent')
        ->assertHasErrors(['eventEndDatetime']);
});

test('can create project through modal', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'project')
        ->set('projectName', 'New Project')
        ->set('projectDescription', 'Project description')
        ->call('createProject')
        ->assertDispatched('project-created');

    expect(Project::where('name', 'New Project')->exists())->toBeTrue();
});

test('project creation requires name', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'project')
        ->set('projectName', '')
        ->call('createProject')
        ->assertHasErrors(['projectName']);
});

test('calendar displays events for current month', function () {
    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Monthly Event',
        'start_datetime' => now(),
        'end_datetime' => now()->addHour(),
    ]);

    actingAs($this->user);

    Livewire::test('workspace.calendar-view')
        ->assertSee(now()->format('F Y'))
        ->assertSee('Monthly Event');
});

test('calendar can navigate to next month', function () {
    actingAs($this->user);

    $currentMonth = now()->format('F Y');
    $nextMonth = now()->addMonth()->format('F Y');

    Livewire::test('workspace.calendar-view')
        ->assertSee($currentMonth)
        ->call('nextMonth')
        ->assertSee($nextMonth);
});

test('calendar can navigate to previous month', function () {
    actingAs($this->user);

    $currentMonth = now()->format('F Y');
    $previousMonth = now()->subMonth()->format('F Y');

    Livewire::test('workspace.calendar-view')
        ->assertSee($currentMonth)
        ->call('previousMonth')
        ->assertSee($previousMonth);
});

test('calendar can jump to today', function () {
    actingAs($this->user);

    Livewire::test('workspace.calendar-view')
        ->call('nextMonth')
        ->call('nextMonth')
        ->call('goToToday')
        ->assertSee(now()->format('F Y'));
});

// Phase 2 Tests

test('can switch between list and kanban views', function () {
    actingAs($this->user);

    Livewire::test('workspace.show-items')
        ->assertSet('viewMode', 'list')
        ->call('switchView', 'kanban')
        ->assertSet('viewMode', 'kanban')
        ->call('switchView', 'list')
        ->assertSet('viewMode', 'list');
});

test('can update task status via kanban drag and drop', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Draggable Task',
        'status' => 'to_do',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-items')
        ->call('updateTaskStatus', $task->id, 'doing')
        ->assertDispatched('task-updated');

    expect($task->fresh()->status->value)->toBe('doing');
});

test('can bulk change status', function () {
    $task1 = Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'to_do',
    ]);

    $task2 = Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'to_do',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-items')
        ->set('selectedItems', [$task1->id.'-task', $task2->id.'-task'])
        ->call('bulkChangeStatus', 'done');

    expect($task1->fresh()->status->value)->toBe('done');
    expect($task2->fresh()->status->value)->toBe('done');
});

test('can bulk change priority', function () {
    $task1 = Task::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'low',
    ]);

    $task2 = Task::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'medium',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-items')
        ->set('selectedItems', [$task1->id.'-task', $task2->id.'-task'])
        ->call('bulkChangePriority', 'urgent');

    expect($task1->fresh()->priority->value)->toBe('urgent');
    expect($task2->fresh()->priority->value)->toBe('urgent');
});

test('can bulk delete items', function () {
    $task1 = Task::factory()->create(['user_id' => $this->user->id]);
    $task2 = Task::factory()->create(['user_id' => $this->user->id]);

    actingAs($this->user);

    Livewire::test('workspace.show-items')
        ->set('selectedItems', [$task1->id.'-task', $task2->id.'-task'])
        ->call('bulkDelete');

    expect(Task::find($task1->id))->toBeNull();
    expect(Task::find($task2->id))->toBeNull();
});

test('can open and view task detail modal', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Detail Task',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-task-detail')
        ->dispatch('view-task-detail', id: $task->id)
        ->assertSet('isOpen', true)
        ->assertSee('Detail Task');
});

test('list view updates after creating new task and does not disappear', function () {
    // Create an existing task to ensure it remains visible
    $existingTask = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Existing Task',
        'status' => 'to_do',
        'start_date' => now()->toDateString(),
    ]);

    actingAs($this->user);

    // Test show-items component initially shows existing task
    $component = Livewire::test('workspace.show-items')
        ->assertSee('Existing Task')
        ->assertSet('viewMode', 'list');

    // Create a new task via the modal
    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'task')
        ->set('taskTitle', 'New Task After Creation')
        ->set('taskStatus', 'to_do')
        ->set('taskStartDate', now()->toDateString())
        ->call('createTask')
        ->assertDispatched('item-updated');

    // Verify the new task exists in database
    expect(Task::where('title', 'New Task After Creation')->exists())->toBeTrue();

    // Test show-items component again - it should refresh and show both tasks
    // We dispatch the item-updated event to simulate what happens in real usage
    $component->dispatch('item-updated')
        ->assertSee('Existing Task')
        ->assertSee('New Task After Creation');
});

test('list view updates after creating new event and does not disappear', function () {
    // Create an existing event
    $existingEvent = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Existing Event',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->assertSee('Existing Event')
        ->assertSet('viewMode', 'list');

    // Create a new event
    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'event')
        ->set('eventTitle', 'New Event After Creation')
        ->set('eventStartDatetime', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->set('eventEndDatetime', now()->addDays(2)->addHour()->format('Y-m-d\TH:i'))
        ->call('createEvent')
        ->assertDispatched('item-updated');

    expect(Event::where('title', 'New Event After Creation')->exists())->toBeTrue();

    // Verify both events are visible after refresh
    $component->dispatch('item-updated')
        ->assertSee('Existing Event')
        ->assertSee('New Event After Creation');
});

test('list view updates after creating new project and does not disappear', function () {
    // Create an existing project
    $existingProject = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Existing Project',
        'start_date' => now()->toDateString(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->assertSee('Existing Project')
        ->assertSet('viewMode', 'list');

    // Create a new project
    Livewire::test('workspace.create-item-modal')
        ->set('activeTab', 'project')
        ->set('projectName', 'New Project After Creation')
        ->set('projectStartDate', now()->toDateString())
        ->call('createProject')
        ->assertDispatched('item-updated');

    expect(Project::where('name', 'New Project After Creation')->exists())->toBeTrue();

    // Verify both projects are visible after refresh
    $component->dispatch('item-updated')
        ->assertSee('Existing Project')
        ->assertSee('New Project After Creation');
});

test('can edit task in detail modal', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'description' => 'Original Description',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-task-detail')
        ->dispatch('view-task-detail', id: $task->id)
        ->call('toggleEditMode')
        ->assertSet('editMode', true)
        ->set('title', 'Updated Title')
        ->set('description', 'Updated Description')
        ->call('save')
        ->assertDispatched('task-updated');

    expect($task->fresh()->title)->toBe('Updated Title');
    expect($task->fresh()->description)->toBe('Updated Description');
});

test('can delete task from detail modal', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-task-detail')
        ->dispatch('view-task-detail', id: $task->id)
        ->call('deleteTask')
        ->assertDispatched('task-deleted');

    expect(Task::find($task->id))->toBeNull();
});

test('can open and view event detail modal', function () {
    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Detail Event',
        'start_datetime' => now(),
        'end_datetime' => now()->addHour(),
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-event-detail')
        ->dispatch('view-event-detail', id: $event->id)
        ->assertSet('isOpen', true)
        ->assertSee('Detail Event');
});

test('can edit event in detail modal', function () {
    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Original Event',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-event-detail')
        ->dispatch('view-event-detail', id: $event->id)
        ->call('toggleEditMode')
        ->set('title', 'Updated Event')
        ->call('save')
        ->assertDispatched('event-updated');

    expect($event->fresh()->title)->toBe('Updated Event');
});

test('can open and view project detail modal', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Detail Project',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-project-detail')
        ->dispatch('view-project-detail', id: $project->id)
        ->assertSet('isOpen', true)
        ->assertSee('Detail Project');
});

test('can edit project in detail modal', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Original Project',
        'description' => 'Original Description',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-project-detail')
        ->dispatch('view-project-detail', id: $project->id)
        ->call('toggleEditMode')
        ->set('name', 'Updated Project')
        ->call('save')
        ->assertDispatched('project-updated');

    expect($project->fresh()->name)->toBe('Updated Project');
});

test('project detail shows task progress', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Progress Project',
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'status' => 'done',
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'status' => 'to_do',
    ]);

    actingAs($this->user);

    Livewire::test('workspace.show-project-detail')
        ->dispatch('view-project-detail', id: $project->id)
        ->assertSee('1 of 2 tasks completed')
        ->assertSee('50%');
});

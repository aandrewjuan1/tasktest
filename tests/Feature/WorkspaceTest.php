<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

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
        ->assertSeeLivewire('workspace.create-item');
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

    Livewire::test('workspace.create-item')
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

    Livewire::test('workspace.create-item')
        ->set('activeTab', 'task')
        ->set('taskTitle', '')
        ->set('taskStatus', 'to_do')
        ->call('createTask')
        ->assertHasErrors(['taskTitle']);
});

test('can create event through modal', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item')
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

    Livewire::test('workspace.create-item')
        ->set('activeTab', 'event')
        ->set('eventTitle', '')
        ->call('createEvent')
        ->assertHasErrors(['eventTitle', 'eventStartDatetime', 'eventEndDatetime']);
});

test('event end datetime must be after start datetime', function () {
    actingAs($this->user);

    $start = now()->addDay();
    $end = now()->addHour();

    Livewire::test('workspace.create-item')
        ->set('activeTab', 'event')
        ->set('eventTitle', 'Test Event')
        ->set('eventStartDatetime', $start->format('Y-m-d H:i'))
        ->set('eventEndDatetime', $end->format('Y-m-d H:i'))
        ->call('createEvent')
        ->assertHasErrors(['eventEndDatetime']);
});

test('can create project through modal', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item')
        ->set('activeTab', 'project')
        ->set('projectName', 'New Project')
        ->set('projectDescription', 'Project description')
        ->call('createProject')
        ->assertDispatched('project-created');

    expect(Project::where('name', 'New Project')->exists())->toBeTrue();
});

test('project creation requires name', function () {
    actingAs($this->user);

    Livewire::test('workspace.create-item')
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
    Livewire::test('workspace.create-item')
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
    Livewire::test('workspace.create-item')
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
    Livewire::test('workspace.create-item')
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

test('show-items component remains visible and refreshes after task deletion', function () {
    $task1 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task to Delete',
        'start_date' => now()->toDateString(),
    ]);

    $task2 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Remaining Task',
        'start_date' => now()->toDateString(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->assertSet('viewMode', 'list');

    // Verify component is visible by checking for view switcher
    $component->assertSee('List View');

    // Delete the task via the detail modal
    Livewire::test('workspace.show-task-detail')
        ->dispatch('view-task-detail', id: $task1->id)
        ->call('deleteTask')
        ->assertDispatched('task-deleted');

    // Verify show-items component refreshes and remains visible
    $component->dispatch('task-deleted')
        ->assertSee('List View') // Component is still visible
        ->assertSet('viewMode', 'list');
});

test('show-items component remains visible and refreshes after event deletion', function () {
    $event1 = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Event to Delete',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
    ]);

    $event2 = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Remaining Event',
        'start_datetime' => now()->addDays(2),
        'end_datetime' => now()->addDays(2)->addHour(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->assertSet('viewMode', 'list');

    // Verify component is visible by checking for view switcher
    $component->assertSee('List View');

    // Delete the event via the detail modal
    Livewire::test('workspace.show-event-detail')
        ->dispatch('view-event-detail', id: $event1->id)
        ->call('deleteEvent')
        ->assertDispatched('event-deleted');

    // Verify show-items component refreshes and remains visible
    $component->dispatch('event-deleted')
        ->assertSee('List View') // Component is still visible
        ->assertSet('viewMode', 'list');
});

test('show-items component remains visible and refreshes after project deletion', function () {
    $project1 = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Project to Delete',
        'start_date' => now()->toDateString(),
    ]);

    $project2 = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Remaining Project',
        'start_date' => now()->toDateString(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->assertSet('viewMode', 'list');

    // Verify component is visible by checking for view switcher
    $component->assertSee('List View');

    // Delete the project via the detail modal
    Livewire::test('workspace.show-project-detail')
        ->dispatch('view-project-detail', id: $project1->id)
        ->call('deleteProject')
        ->assertDispatched('project-deleted');

    // Verify show-items component refreshes and remains visible
    $component->dispatch('project-deleted')
        ->assertSee('List View') // Component is still visible
        ->assertSet('viewMode', 'list');
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

// Filtering and Sorting Tests

test('filter by task type shows only tasks', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Task',
        'start_datetime' => now(),
    ]);

    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Event',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Filtered Project',
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterType', 'task');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->item_type)->toBe('task');
    expect($items->first()->title)->toBe('Filtered Task');
});

test('filter by event type shows only events', function () {
    $targetDate = now()->addDay();

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Task',
        'start_datetime' => $targetDate,
    ]);

    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Event',
        'start_datetime' => $targetDate,
        'end_datetime' => $targetDate->copy()->addHour(),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Filtered Project',
        'start_date' => $targetDate->toDateString(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', 'event');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->item_type)->toBe('event');
    expect($items->first()->title)->toBe('Filtered Event');
});

test('filter by project type shows only projects', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Task',
        'start_datetime' => now(),
    ]);

    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Event',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Filtered Project',
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterType', 'project');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->item_type)->toBe('project');
    expect($items->first()->name)->toBe('Filtered Project');
});

test('filter by all or null shows all types', function () {
    $targetDate = now()->addDay();

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Task',
        'start_datetime' => $targetDate,
    ]);

    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Filtered Event',
        'start_datetime' => $targetDate,
        'end_datetime' => $targetDate->copy()->addHour(),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Filtered Project',
        'start_date' => $targetDate->toDateString(),
    ]);

    actingAs($this->user);

    // Test null filter
    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', null);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(3);

    // Test 'all' filter
    $component->call('setFilterType', 'all');
    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(3);
});

test('filter by priority works for tasks', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'urgent',
        'title' => 'Urgent Task',
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'low',
        'title' => 'Low Task',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterPriority', 'urgent');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->priority->value)->toBe('urgent');
    expect($items->first()->title)->toBe('Urgent Task');
});

test('filter by priority is ignored for events and projects', function () {
    $targetDate = now()->addDay();

    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Test Event',
        'start_datetime' => $targetDate,
        'end_datetime' => $targetDate->copy()->addHour(),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
        'start_date' => $targetDate->toDateString(),
    ]);

    actingAs($this->user);

    // Filter by priority should not affect events/projects
    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', 'event')
        ->call('setFilterPriority', 'urgent');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->item_type)->toBe('event');

    $component->call('setFilterType', 'project')
        ->call('setFilterPriority', 'urgent');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->item_type)->toBe('project');
});

test('filter by status works for tasks', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'to_do',
        'title' => 'To Do Task',
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'done',
        'title' => 'Done Task',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterStatus', 'to_do');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->status->value)->toBe('to_do');
    expect($items->first()->title)->toBe('To Do Task');
});

test('filter by status works for events', function () {
    $targetDate = now()->addDay();

    Event::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'scheduled',
        'title' => 'Scheduled Event',
        'start_datetime' => $targetDate,
        'end_datetime' => $targetDate->copy()->addHour(),
    ]);

    Event::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'title' => 'Completed Event',
        'start_datetime' => $targetDate,
        'end_datetime' => $targetDate->copy()->addHour(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', 'event')
        ->call('setFilterStatus', 'scheduled');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->status->value)->toBe('scheduled');
    expect($items->first()->title)->toBe('Scheduled Event');
});

test('filter by project shows tasks in that project', function () {
    $targetDate = now();

    $project1 = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Project 1',
    ]);

    $project2 = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Project 2',
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project1->id,
        'title' => 'Task in Project 1',
        'start_datetime' => $targetDate,
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project2->id,
        'title' => 'Task in Project 2',
        'start_datetime' => $targetDate->copy()->addDay(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', 'task')
        ->call('setFilterProject', $project1->id);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Task in Project 1');
    expect($items->first()->project_id)->toBe($project1->id);
});

test('filter by project shows events with tasks in that project', function () {
    $targetDate = now()->addDay();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Event with Task',
        'start_datetime' => $targetDate,
        'end_datetime' => $targetDate->copy()->addHour(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'event_id' => $event->id,
        'title' => 'Task in Project',
        'start_datetime' => $targetDate,
    ]);

    Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Event without Task',
        'start_datetime' => $targetDate,
        'end_datetime' => $targetDate->copy()->addHour(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', 'event')
        ->call('setFilterProject', $project->id);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Event with Task');
});

test('filter by project on projects view shows only that project', function () {
    $targetDate = now();

    $project1 = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Project 1',
        'start_date' => $targetDate->toDateString(),
    ]);

    $project2 = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Project 2',
        'start_date' => $targetDate->toDateString(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', 'project')
        ->call('setFilterProject', $project1->id);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->id)->toBe($project1->id);
    expect($items->first()->name)->toBe('Project 1');
});

test('filter by tags works', function () {
    $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
    $tag2 = Tag::factory()->create(['name' => 'Tag 2']);

    $task1 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task with Tag 1',
        'start_datetime' => now(),
    ]);
    $task1->tags()->attach($tag1->id);

    $task2 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task with Tag 2',
        'start_datetime' => now(),
    ]);
    $task2->tags()->attach($tag2->id);

    $task3 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task with Both Tags',
        'start_datetime' => now(),
    ]);
    $task3->tags()->attach([$tag1->id, $tag2->id]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('toggleFilterTag', $tag1->id);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(2);
    expect($items->pluck('title')->toArray())->toContain('Task with Tag 1');
    expect($items->pluck('title')->toArray())->toContain('Task with Both Tags');
});

test('filter by multiple tags works with AND logic', function () {
    $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
    $tag2 = Tag::factory()->create(['name' => 'Tag 2']);

    $task1 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task with Tag 1',
        'start_datetime' => now(),
    ]);
    $task1->tags()->attach($tag1->id);

    $task2 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task with Tag 2',
        'start_datetime' => now(),
    ]);
    $task2->tags()->attach($tag2->id);

    $task3 = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task with Both Tags',
        'start_datetime' => now(),
    ]);
    $task3->tags()->attach([$tag1->id, $tag2->id]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('toggleFilterTag', $tag1->id)
        ->call('toggleFilterTag', $tag2->id);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Task with Both Tags');
});

test('toggle tag filter adds and removes correctly', function () {
    $tag = Tag::factory()->create(['name' => 'Test Tag']);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Tagged Task',
        'start_datetime' => now(),
    ]);
    $task->tags()->attach($tag->id);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Untagged Task',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items');
    expect($component->get('filteredItems'))->toHaveCount(2);

    // Add tag filter
    $component->call('toggleFilterTag', $tag->id);
    expect($component->get('filteredItems'))->toHaveCount(1);
    expect($component->get('filteredItems')->first()->title)->toBe('Tagged Task');

    // Remove tag filter
    $component->call('toggleFilterTag', $tag->id);
    expect($component->get('filteredItems'))->toHaveCount(2);
});

test('date filter works for list view', function () {
    $targetDate = now()->addDay();

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task on Target Date',
        'start_datetime' => $targetDate->copy()->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->setTime(11, 0),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task on Different Date',
        'start_datetime' => $targetDate->copy()->addDay()->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->addDay()->setTime(11, 0),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('viewMode', 'list')
        ->set('currentDate', $targetDate);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Task on Target Date');
});

test('date filter works for kanban view', function () {
    $targetDate = now()->addDay();

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task on Target Date',
        'start_datetime' => $targetDate->copy()->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->setTime(11, 0),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task on Different Date',
        'start_datetime' => $targetDate->copy()->addDay()->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->addDay()->setTime(11, 0),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('viewMode', 'kanban')
        ->set('currentDate', $targetDate);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Task on Target Date');
});

test('date filter shows items spanning that date', function () {
    $targetDate = now()->addDay();

    // Task that spans the target date
    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Spanning Task',
        'start_datetime' => $targetDate->copy()->subDay()->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->addDay()->setTime(10, 0),
    ]);

    // Task that doesn't span the target date
    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Non-Spanning Task',
        'start_datetime' => $targetDate->copy()->addDays(2)->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->addDays(2)->setTime(11, 0),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('viewMode', 'list')
        ->set('currentDate', $targetDate);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Spanning Task');
});

test('date filter not applied for weekly view', function () {
    $targetDate = now()->addDay();

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task on Target Date',
        'start_datetime' => $targetDate->copy()->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->setTime(11, 0),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task on Different Date',
        'start_datetime' => $targetDate->copy()->addDays(2)->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->addDays(2)->setTime(11, 0),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('viewMode', 'weekly')
        ->set('currentDate', $targetDate);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(2); // Both tasks should be visible
});

test('sort by priority works for tasks', function () {
    $targetDate = now();

    Task::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'low',
        'title' => 'Low Priority Task',
        'start_datetime' => $targetDate,
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'urgent',
        'title' => 'Urgent Task',
        'start_datetime' => $targetDate,
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'high',
        'title' => 'High Priority Task',
        'start_datetime' => $targetDate,
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setSortBy', 'priority')
        ->set('sortDirection', 'desc');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(3);
    expect($items->first()->title)->toBe('Urgent Task');
    expect($items->last()->title)->toBe('Low Priority Task');
});

test('sort by created_at works', function () {
    $oldTask = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Old Task',
        'created_at' => now()->subDays(2),
        'start_datetime' => now(),
    ]);

    $newTask = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'New Task',
        'created_at' => now(),
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setSortBy', 'created_at')
        ->set('sortDirection', 'desc');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(2);
    expect($items->first()->title)->toBe('New Task');
    expect($items->last()->title)->toBe('Old Task');
});

test('sort by start_datetime works', function () {
    $targetDate = now()->addDay();

    // Create tasks that span the target date so both are included
    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Later Task',
        'start_datetime' => $targetDate->copy()->setTime(14, 0),
        'end_datetime' => $targetDate->copy()->setTime(15, 0),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Earlier Task',
        'start_datetime' => $targetDate->copy()->setTime(10, 0),
        'end_datetime' => $targetDate->copy()->setTime(11, 0),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setSortBy', 'start_datetime')
        ->set('sortDirection', 'asc');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(2);
    expect($items->first()->title)->toBe('Earlier Task');
    expect($items->last()->title)->toBe('Later Task');
});

test('sort by title works', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Zebra Task',
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Apple Task',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setSortBy', 'title')
        ->set('sortDirection', 'asc');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(2);
    expect($items->first()->title)->toBe('Apple Task');
    expect($items->last()->title)->toBe('Zebra Task');
});

test('sort direction toggle works', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Zebra Task',
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Apple Task',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setSortBy', 'title');

    // First call sets to asc
    expect($component->get('sortDirection'))->toBe('asc');
    $items = $component->get('filteredItems');
    expect($items->first()->title)->toBe('Apple Task');

    // Second call toggles to desc
    $component->call('setSortBy', 'title');
    expect($component->get('sortDirection'))->toBe('desc');
    $items = $component->get('filteredItems');
    expect($items->first()->title)->toBe('Zebra Task');
});

test('default sort when no sortBy is set', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Old Task',
        'created_at' => now()->subDays(2),
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'New Task',
        'created_at' => now(),
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('sortBy', null);

    // Default should be created_at desc
    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(2);
    expect($items->first()->title)->toBe('New Task');
});

test('multiple filters work together', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    $tag = Tag::factory()->create(['name' => 'Test Tag']);

    $task1 = Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'priority' => 'urgent',
        'status' => 'to_do',
        'title' => 'Matching Task',
        'start_datetime' => now(),
    ]);
    $task1->tags()->attach($tag->id);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'priority' => 'urgent',
        'status' => 'to_do',
        'title' => 'No Tag Task',
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'priority' => 'low',
        'status' => 'to_do',
        'title' => 'Low Priority Task',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterType', 'task')
        ->call('setFilterPriority', 'urgent')
        ->call('setFilterStatus', 'to_do')
        ->call('setFilterProject', $project->id)
        ->call('toggleFilterTag', $tag->id);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Matching Task');
});

test('filters and sorting work together', function () {
    $targetDate = now();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'priority' => 'low',
        'title' => 'Low Priority Task',
        'start_datetime' => $targetDate,
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'priority' => 'urgent',
        'title' => 'Urgent Task',
        'start_datetime' => $targetDate,
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('currentDate', $targetDate)
        ->call('setFilterType', 'task')
        ->call('setFilterProject', $project->id)
        ->call('setSortBy', 'priority')
        ->set('sortDirection', 'desc');

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(2);
    expect($items->first()->title)->toBe('Urgent Task');
    expect($items->last()->title)->toBe('Low Priority Task');
});

test('clearing filters works', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'title' => 'Task in Project',
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task without Project',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterProject', $project->id);

    expect($component->get('filteredItems'))->toHaveCount(1);

    $component->call('clearFilters');
    expect($component->get('filteredItems'))->toHaveCount(2);
    expect($component->get('filterProject'))->toBeNull();
});

test('clearing sorting works', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task 1',
        'start_datetime' => now(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task 2',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setSortBy', 'title');

    expect($component->get('sortBy'))->toBe('title');

    $component->call('clearSorting');
    expect($component->get('sortBy'))->toBeNull();
    expect($component->get('sortDirection'))->toBe('asc');
});

test('clear all works', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'title' => 'Task',
        'start_datetime' => now(),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterProject', $project->id)
        ->call('setSortBy', 'title');

    expect($component->get('filterProject'))->not->toBeNull();
    expect($component->get('sortBy'))->not->toBeNull();

    $component->call('clearAll');
    expect($component->get('filterProject'))->toBeNull();
    expect($component->get('sortBy'))->toBeNull();
});

test('switching views clears filters and sorts', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->call('setFilterProject', $project->id)
        ->call('setSortBy', 'title');

    expect($component->get('filterProject'))->not->toBeNull();
    expect($component->get('sortBy'))->not->toBeNull();

    $component->call('switchView', 'kanban');
    expect($component->get('filterProject'))->toBeNull();
    expect($component->get('sortBy'))->toBeNull();
});

test('weekly view applies filters but not date filter', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'title' => 'Task in Project',
        'start_datetime' => now()->addDay(),
    ]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Task without Project',
        'start_datetime' => now()->addDays(2),
    ]);

    actingAs($this->user);

    $component = Livewire::test('workspace.show-items')
        ->set('viewMode', 'weekly')
        ->call('setFilterType', 'task')
        ->call('setFilterProject', $project->id);

    $items = $component->get('filteredItems');
    expect($items)->toHaveCount(1);
    expect($items->first()->title)->toBe('Task in Project');
});

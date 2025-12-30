<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

test('weekly view can be switched to', function () {
    Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->assertSet('viewMode', 'weekly');
});

test('weekly view displays current week by default', function () {
    $component = Volt::test('workspace.show-items');

    expect($component->get('weekStartDate')->format('Y-m-d'))
        ->toBe(now()->startOfWeek()->format('Y-m-d'));
});

test('can navigate to previous week', function () {
    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly');

    $initialWeek = $component->get('weekStartDate');

    $component->call('previousWeek');

    expect($component->get('weekStartDate')->format('Y-m-d'))
        ->toBe($initialWeek->copy()->subWeek()->format('Y-m-d'));
});

test('can navigate to next week', function () {
    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly');

    $initialWeek = $component->get('weekStartDate');

    $component->call('nextWeek');

    expect($component->get('weekStartDate')->format('Y-m-d'))
        ->toBe($initialWeek->copy()->addWeek()->format('Y-m-d'));
});

test('can navigate to today', function () {
    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->call('previousWeek')
        ->call('previousWeek');

    $component->call('goToToday');

    expect($component->get('weekStartDate')->format('Y-m-d'))
        ->toBe(now()->startOfWeek()->format('Y-m-d'));
});

test('weekly view displays events in current week', function () {
    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Test Event',
        'start_datetime' => now()->startOfWeek()->addDays(2)->setHour(10),
        'end_datetime' => now()->startOfWeek()->addDays(2)->setHour(11),
        'all_day' => false,
    ]);

    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->assertSee('Test Event');

    $weeklyItems = $component->get('weeklyItems');
    $dateKey = $event->start_datetime->format('Y-m-d');

    expect($weeklyItems[$dateKey]['timed'])->toHaveCount(1);
    expect($weeklyItems[$dateKey]['timed']->first()->title)->toBe('Test Event');
});

test('weekly view displays all-day events in all-day section', function () {
    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'All Day Event',
        'start_datetime' => now()->startOfWeek()->addDays(1)->startOfDay(),
        'end_datetime' => now()->startOfWeek()->addDays(1)->endOfDay(),
        'all_day' => true,
    ]);

    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->assertSee('All Day Event');

    $weeklyItems = $component->get('weeklyItems');
    $dateKey = $event->start_datetime->format('Y-m-d');

    expect($weeklyItems[$dateKey]['all_day'])->toHaveCount(1);
    expect($weeklyItems[$dateKey]['all_day']->first()->title)->toBe('All Day Event');
});

test('weekly view displays tasks in all-day section', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Test Task',
        'start_date' => now()->startOfWeek()->addDays(3),
        'status' => 'to_do',
    ]);

    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->assertSee('Test Task');

    $weeklyItems = $component->get('weeklyItems');
    $dateKey = $task->start_date->format('Y-m-d');

    expect($weeklyItems[$dateKey]['all_day'])->toHaveCount(1);
    expect($weeklyItems[$dateKey]['all_day']->first()->title)->toBe('Test Task');
});

test('weekly view displays projects in all-day section', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Project',
        'start_date' => now()->startOfWeek()->addDays(4),
    ]);

    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->assertSee('Test Project');

    $weeklyItems = $component->get('weeklyItems');
    $dateKey = $project->start_date->format('Y-m-d');

    expect($weeklyItems[$dateKey]['all_day'])->toHaveCount(1);
});

test('can update item datetime via drag and drop', function () {
    $event = Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Draggable Event',
        'start_datetime' => now()->startOfWeek()->addDays(2)->setHour(10),
        'end_datetime' => now()->startOfWeek()->addDays(2)->setHour(11),
    ]);

    $newStartTime = now()->startOfWeek()->addDays(3)->setHour(14)->setMinute(30)->format('Y-m-d H:i:s');
    $newEndTime = now()->startOfWeek()->addDays(3)->setHour(15)->setMinute(30)->format('Y-m-d H:i:s');

    Volt::test('workspace.show-items')
        ->call('updateItemDateTime', $event->id, 'event', $newStartTime, $newEndTime);

    $event->refresh();

    expect($event->start_datetime->format('Y-m-d H:i'))->toBe(now()->startOfWeek()->addDays(3)->setHour(14)->setMinute(30)->format('Y-m-d H:i'));
    expect($event->end_datetime->format('Y-m-d H:i'))->toBe(now()->startOfWeek()->addDays(3)->setHour(15)->setMinute(30)->format('Y-m-d H:i'));
});

test('filters apply to weekly view', function () {
    Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'HighPriority',
        'start_datetime' => now()->startOfWeek()->addDays(2)->setHour(10),
        'end_datetime' => now()->startOfWeek()->addDays(2)->setHour(11),
        'status' => 'scheduled',
    ]);

    Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'LowPriority',
        'start_datetime' => now()->startOfWeek()->addDays(2)->setHour(14),
        'end_datetime' => now()->startOfWeek()->addDays(2)->setHour(15),
        'status' => 'tentative',
    ]);

    $component = Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->set('statusFilter', 'scheduled')
        ->assertSee('HighPriority')
        ->assertDontSee('LowPriority');
});

test('search applies to weekly view', function () {
    Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Team Meeting',
        'start_datetime' => now()->startOfWeek()->addDays(2)->setHour(10),
        'end_datetime' => now()->startOfWeek()->addDays(2)->setHour(11),
    ]);

    Event::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Client Call',
        'start_datetime' => now()->startOfWeek()->addDays(2)->setHour(14),
        'end_datetime' => now()->startOfWeek()->addDays(2)->setHour(15),
    ]);

    Volt::test('workspace.show-items')
        ->call('switchView', 'weekly')
        ->set('search', 'Team')
        ->assertSee('Team Meeting')
        ->assertDontSee('Client Call');
});

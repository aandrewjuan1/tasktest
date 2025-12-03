<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;

test('user can soft delete their event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create();

    $this->actingAs($user);

    $event->delete();

    expect(Event::find($event->id))->toBeNull();
    expect(Event::withTrashed()->find($event->id))->not->toBeNull();
    expect(Event::withTrashed()->find($event->id)->trashed())->toBeTrue();
});

test('user can restore a soft deleted event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create(['title' => 'Restorable Event']);

    $event->delete();

    expect(Event::find($event->id))->toBeNull();
    expect(Event::withTrashed()->find($event->id)->trashed())->toBeTrue();

    $event->restore();

    expect(Event::find($event->id))->not->toBeNull();
    expect(Event::find($event->id)->trashed())->toBeFalse();
});

test('user can force delete an event permanently', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create(['title' => 'Permanently Deleted Event']);

    $eventId = $event->id;
    $event->forceDelete();

    expect(Event::find($eventId))->toBeNull();
    expect(Event::withTrashed()->find($eventId))->toBeNull();
});

test('soft deleted events can be queried with withTrashed', function () {
    $user = User::factory()->create();

    $activeEvent = Event::factory()->for($user)->create(['title' => 'Active']);
    $deletedEvent = Event::factory()->for($user)->create(['title' => 'Deleted']);
    $deletedEvent->delete();

    $allEvents = Event::withTrashed()->where('user_id', $user->id)->get();
    $onlyDeleted = Event::onlyTrashed()->where('user_id', $user->id)->get();

    expect($allEvents)->toHaveCount(2);
    expect($onlyDeleted)->toHaveCount(1);
    expect($onlyDeleted->first()->title)->toBe('Deleted');
});

test('events page displays only non-deleted events', function () {
    $user = User::factory()->create();

    $activeEvent = Event::factory()->for($user)->create(['title' => 'Active Event']);
    $deletedEvent = Event::factory()->for($user)->create(['title' => 'Deleted Event']);
    $deletedEvent->delete();

    $events = Event::where('user_id', $user->id)->get();

    expect($events)->toHaveCount(1);
    expect($events->first()->title)->toBe('Active Event');
});

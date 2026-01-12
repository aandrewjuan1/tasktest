<?php

namespace App\Observers;

use App\Enums\EventStatus;
use App\Events\EventCreated;
use App\Events\EventDeleted;
use App\Events\EventUpdated;
use App\Models\Event;

class EventObserver
{
    public function updating(Event $event): void
    {
        // Set completed_at when status changes to Completed
        if ($event->isDirty('status') && $event->status === EventStatus::Completed) {
            // Note: Event model doesn't have completed_at field directly, but EventInstance does
            // This is handled in EventInstance updates
        }
    }

    public function created(Event $event): void
    {
        EventCreated::dispatch($event);
    }

    public function updated(Event $event): void
    {
        EventUpdated::dispatch($event);
    }

    public function deleted(Event $event): void
    {
        EventDeleted::dispatch($event);
    }
}

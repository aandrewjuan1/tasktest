<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Event $event
    ) {
    }
}

<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\RecurrenceType;
use App\Models\Event;
use App\Models\EventInstance;
use App\Models\RecurringEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function createEvent(array $data, int $userId): Event
    {
        $startDatetime = ! empty($data['startDatetime'])
            ? Carbon::parse($data['startDatetime'])
            : null;

        $endDatetime = ! empty($data['endDatetime'])
            ? Carbon::parse($data['endDatetime'])
            : null;

        return DB::transaction(function () use ($data, $startDatetime, $endDatetime, $userId) {
            $event = Event::create([
                'user_id' => $userId,
                'title' => $data['title'],
                'status' => $data['status'] ? EventStatus::from($data['status']) : null,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
            ]);

            if (! empty($data['tagIds'])) {
                $event->tags()->attach($data['tagIds']);
            }

            // Create recurring event if enabled
            if (! empty($data['recurrence']['enabled']) && $data['recurrence']['enabled']) {
                RecurringEvent::create([
                    'event_id' => $event->id,
                    'recurrence_type' => RecurrenceType::from($data['recurrence']['type']),
                    'interval' => $data['recurrence']['interval'] ?? 1,
                    'start_datetime' => $event->start_datetime ?? now(),
                    'end_datetime' => $event->end_datetime,
                    'days_of_week' => ! empty($data['recurrence']['daysOfWeek'])
                        ? implode(',', $data['recurrence']['daysOfWeek'])
                        : null,
                ]);
            }

            return $event;
        });
    }

    public function updateEventField(Event $event, string $field, mixed $value, ?string $instanceDate = null): void
    {
        DB::transaction(function () use ($event, $field, $value, $instanceDate) {
            // Handle status updates for recurring events with instance_date
            if ($field === 'status' && $event->recurringEvent && $instanceDate) {
                $statusEnum = match ($value) {
                    'to_do', 'scheduled' => EventStatus::Scheduled,
                    'doing', 'ongoing' => EventStatus::Ongoing,
                    'done', 'completed' => EventStatus::Completed,
                    'cancelled' => EventStatus::Cancelled,
                    'tentative' => EventStatus::Tentative,
                    default => null,
                };

                if ($statusEnum) {
                    // Parse date and normalize
                    $parsedDate = Carbon::parse($instanceDate)->startOfDay();
                    $dateString = $parsedDate->format('Y-m-d');

                    // Check if instance exists for this specific event_id and date
                    $existingInstance = EventInstance::where('event_id', $event->id)
                        ->whereDate('instance_date', $dateString)
                        ->first();

                    if ($existingInstance) {
                        // Update existing instance
                        $existingInstance->update([
                            'status' => $statusEnum,
                            'cancelled' => $statusEnum === EventStatus::Cancelled,
                            'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                        ]);
                    } else {
                        // Create new instance
                        EventInstance::create([
                            'recurring_event_id' => $event->recurringEvent->id,
                            'event_id' => $event->id,
                            'instance_date' => $dateString,
                            'status' => $statusEnum,
                            'cancelled' => $statusEnum === EventStatus::Cancelled,
                            'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                        ]);
                    }
                }

                return; // Don't update base event for instance status changes
            }

            $updateData = [];

            switch ($field) {
                case 'title':
                    $updateData['title'] = $value;
                    break;
                case 'description':
                    $updateData['description'] = $value ?: null;
                    break;
                case 'startDatetime':
                    if ($value) {
                        $startDatetime = Carbon::parse($value);
                        $updateData['start_datetime'] = $startDatetime;
                        if (! $event->end_datetime) {
                            $updateData['end_datetime'] = $startDatetime->copy()->addHour();
                        }
                    } else {
                        $updateData['start_datetime'] = null;
                    }
                    break;
                case 'endDatetime':
                    $updateData['end_datetime'] = $value ? Carbon::parse($value) : null;
                    break;
                case 'status':
                    $updateData['status'] = $value ?: 'scheduled';
                    break;
                case 'recurrence':
                    // Handle recurrence separately as it involves RecurringEvent model
                    $recurrenceData = $value;

                    if ($recurrenceData === null || empty($recurrenceData) || ! ($recurrenceData['enabled'] ?? false)) {
                        // Delete recurrence if it exists
                        if ($event->recurringEvent) {
                            $event->recurringEvent->delete();
                        }
                    } else {
                        // Create or update recurrence
                        // Ensure start_datetime is never null - use fallback chain
                        $startDatetime = ! empty($recurrenceData['startDatetime'])
                            ? Carbon::parse($recurrenceData['startDatetime'])
                            : ($event->start_datetime
                                ? Carbon::parse($event->start_datetime)
                                : ($event->end_datetime
                                    ? Carbon::parse($event->end_datetime)
                                    : Carbon::now()));

                        $recurringEventData = [
                            'event_id' => $event->id,
                            'recurrence_type' => RecurrenceType::from($recurrenceData['type']),
                            'interval' => $recurrenceData['interval'] ?? 1,
                            'start_datetime' => $startDatetime,
                            'end_datetime' => ! empty($recurrenceData['endDatetime']) ? Carbon::parse($recurrenceData['endDatetime']) : null,
                            'days_of_week' => ! empty($recurrenceData['daysOfWeek']) && is_array($recurrenceData['daysOfWeek'])
                                ? implode(',', $recurrenceData['daysOfWeek'])
                                : null,
                        ];

                        if ($event->recurringEvent) {
                            $event->recurringEvent->update($recurringEventData);
                        } else {
                            RecurringEvent::create($recurringEventData);
                        }
                    }
                    break;
            }

            if (! empty($updateData)) {
                $event->update($updateData);
            }
        });
    }

    public function deleteEvent(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $event->delete();
        });
    }

    public function updateEventStatus(Event $event, string $status, ?string $instanceDate = null): void
    {
        $statusEnum = match ($status) {
            'to_do' => EventStatus::Scheduled,
            'doing' => EventStatus::Ongoing,
            'done' => EventStatus::Completed,
            'scheduled' => EventStatus::Scheduled,
            'ongoing' => EventStatus::Ongoing,
            'completed' => EventStatus::Completed,
            'cancelled' => EventStatus::Cancelled,
            'tentative' => EventStatus::Tentative,
            default => null,
        };

        if (! $statusEnum) {
            return;
        }

        DB::transaction(function () use ($event, $statusEnum, $instanceDate) {
            // Handle status updates for recurring events with instance_date
            if ($event->recurringEvent && $instanceDate) {
                // Parse date and normalize
                $parsedDate = Carbon::parse($instanceDate)->startOfDay();
                $dateString = $parsedDate->format('Y-m-d');

                // Check if instance exists for this specific event_id and date
                $existingInstance = EventInstance::where('event_id', $event->id)
                    ->whereDate('instance_date', $dateString)
                    ->first();

                if ($existingInstance) {
                    // Update existing instance
                    $existingInstance->update([
                        'status' => $statusEnum,
                        'cancelled' => $statusEnum === EventStatus::Cancelled,
                        'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                    ]);
                } else {
                    // Create new instance
                    EventInstance::create([
                        'recurring_event_id' => $event->recurringEvent->id,
                        'event_id' => $event->id,
                        'instance_date' => $dateString,
                        'status' => $statusEnum,
                        'cancelled' => $statusEnum === EventStatus::Cancelled,
                        'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                    ]);
                }
            } else {
                // Update base event for non-recurring events or when no instance date provided
                $event->update(['status' => $statusEnum]);
            }
        });
    }

    public function updateEventDateTime(Event $event, string $start, ?string $end = null): void
    {
        DB::transaction(function () use ($event, $start, $end) {
            if ($start) {
                $event->start_datetime = Carbon::parse($start);
            }
            if ($end) {
                $event->end_datetime = Carbon::parse($end);
            } elseif ($start) {
                // Auto-calculate if start provided but no end
                $event->end_datetime = Carbon::parse($start)->addHour();
            }

            $event->save();
        });
    }

    public function updateEventDuration(Event $event, int $durationMinutes): void
    {
        // Enforce minimum duration of 30 minutes
        $durationMinutes = max(30, $durationMinutes);

        // Snap to 30-minute grid intervals
        $durationMinutes = round($durationMinutes / 30) * 30;
        $durationMinutes = max(30, $durationMinutes); // Ensure still at least 30 after snapping

        DB::transaction(function () use ($event, $durationMinutes) {
            // For events, update end_datetime while keeping start_datetime
            $startDateTime = Carbon::parse($event->start_datetime);
            $event->end_datetime = $startDateTime->copy()->addMinutes($durationMinutes);

            $event->save();
        });
    }

    public function updateEventTags(Event $event, array $tagIds): void
    {
        DB::transaction(function () use ($event, $tagIds) {
            $event->tags()->sync($tagIds);
            $event->refresh();
        });
    }
}

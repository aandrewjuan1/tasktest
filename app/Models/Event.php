<?php

namespace App\Models;

use App\Enums\CollaborationPermission;
use App\Enums\EventStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            // Set default status
            if (is_null($event->status)) {
                $event->status = EventStatus::Scheduled;
            }

            // Dates can be null - no default assignment
            // Auto-calculate end_datetime only if start_datetime is provided but end_datetime is not
            if ($event->start_datetime && is_null($event->end_datetime)) {
                $event->end_datetime = $event->start_datetime->copy()->addHour();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function recurringEvent(): HasOne
    {
        return $this->hasOne(RecurringEvent::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function collaborations(): MorphMany
    {
        return $this->morphMany(Collaboration::class, 'collaboratable');
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function collaborators(): MorphToMany
    {
        return $this->morphToMany(User::class, 'collaboratable', 'collaborations')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function isCollaborator(User $user): bool
    {
        return $this->collaborations()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function getCollaboratorPermission(User $user): ?string
    {
        $collaboration = $this->collaborations()
            ->where('user_id', $user->id)
            ->first();

        return $collaboration?->permission;
    }

    public function canUserEdit(User $user): bool
    {
        // Owner can always edit
        if ($this->user_id === $user->id) {
            return true;
        }

        // Check if user has edit permission
        $permission = $this->getCollaboratorPermission($user);

        return $permission === CollaborationPermission::Edit;
    }

    public function canUserComment(User $user): bool
    {
        // Owner can always comment
        if ($this->user_id === $user->id) {
            return true;
        }

        // Check if user has comment or edit permission
        $permission = $this->getCollaboratorPermission($user);

        return in_array($permission, [CollaborationPermission::Comment, CollaborationPermission::Edit]);
    }

    public function canUserView(User $user): bool
    {
        // Owner can always view
        if ($this->user_id === $user->id) {
            return true;
        }

        // Any collaborator can view
        return $this->isCollaborator($user);
    }

    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('collaborations', fn ($q) => $q->where('user_id', $user->id));
        });
    }

    public function scopeFilterByStatus($query, ?string $status): Builder
    {
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }

    public function scopeFilterByPriority($query, ?string $priority): Builder
    {
        // Events don't have priority, so this is a no-op for interface consistency
        return $query;
    }

    public function scopeDateFilter($query, ?Carbon $date): Builder
    {
        if (! $date) {
            return $query;
        }

        $targetDate = $date->format('Y-m-d');

        return $query->where(function ($q) use ($targetDate) {
            $q->where(function ($dateQ) use ($targetDate) {
                // Items with both start and end datetime
                $dateQ->where(function ($bothQ) use ($targetDate) {
                    $bothQ->whereNotNull('start_datetime')
                        ->whereNotNull('end_datetime')
                        ->where(function ($subQ) use ($targetDate) {
                            $subQ->whereDate('start_datetime', $targetDate)
                                ->orWhereDate('end_datetime', $targetDate)
                                ->orWhere(function ($spanQ) use ($targetDate) {
                                    $spanQ->whereDate('start_datetime', '<=', $targetDate)
                                        ->whereDate('end_datetime', '>=', $targetDate);
                                });
                        });
                })
                // Items with only end_datetime (due date) - show on all days up to and including due date
                    ->orWhere(function ($endOnlyQ) use ($targetDate) {
                        $endOnlyQ->whereNull('start_datetime')
                            ->whereNotNull('end_datetime')
                            ->whereDate('end_datetime', '>=', $targetDate);
                    })
                // Items with only start_datetime - show from start date onwards
                    ->orWhere(function ($startOnlyQ) use ($targetDate) {
                        $startOnlyQ->whereNotNull('start_datetime')
                            ->whereNull('end_datetime')
                            ->whereDate('start_datetime', '<=', $targetDate);
                    })
                // Items with no dates - show on all dates
                    ->orWhere(function ($noDatesQ) {
                        $noDatesQ->whereNull('start_datetime')
                            ->whereNull('end_datetime');
                    });
            });
        });
    }

    public function scopeSortByCreatedAt($query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('created_at', $direction);
    }

    public function scopeSortByStartDatetime($query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('start_datetime', $direction);
    }

    public function scopeSortByEndDatetime($query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('end_datetime', $direction);
    }

    public function scopeSortByTitle($query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('title', $direction);
    }

    public function scopeSortByStatus($query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('status', $direction);
    }

    public function scopeOrderByField($query, ?string $field, string $direction = 'asc'): Builder
    {
        if (! $field) {
            return $query->orderBy('created_at', 'desc');
        }

        return match ($field) {
            'created_at' => $query->orderBy('created_at', $direction),
            'start_datetime' => $query->orderBy('start_datetime', $direction),
            'end_datetime' => $query->orderBy('end_datetime', $direction),
            'title' => $query->orderBy('title', $direction),
            'status' => $query->orderBy('status', $direction),
            'priority' => $query->orderBy('created_at', 'desc'), // Events don't have priority
            default => $query->orderBy('created_at', 'desc'),
        };
    }

    /**
     * Get instances of this event for a given date range.
     * For recurring events, calculates occurrences dynamically.
     * For non-recurring events, returns the event itself if it falls within the range.
     */
    public function getInstancesForDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        // If not recurring, return the event itself if it falls within the date range
        if (! $this->recurringEvent) {
            $isInRange = false;

            if ($this->start_datetime && $this->end_datetime) {
                $eventStart = Carbon::parse($this->start_datetime);
                $eventEnd = Carbon::parse($this->end_datetime);
                // Check if event overlaps with the date range
                $isInRange = $eventStart->lte($endDate) && $eventEnd->gte($startDate);
            } elseif ($this->start_datetime) {
                $isInRange = Carbon::parse($this->start_datetime)->between($startDate, $endDate);
            } else {
                // Event with no dates - show it
                $isInRange = true;
            }

            return $isInRange ? collect([$this]) : collect();
        }

        // For recurring events, calculate occurrences
        $recurringEvent = $this->recurringEvent;
        $occurrences = $recurringEvent->calculateOccurrences($startDate, $endDate);

        // Get exception dates to skip
        $exceptionDates = $recurringEvent->getExceptionDates($startDate, $endDate);

        // Filter out exceptions
        $validOccurrences = $occurrences->reject(function ($occurrence) use ($exceptionDates) {
            return $exceptionDates->contains($occurrence['start']->format('Y-m-d'));
        });

        // Get existing EventInstance records that might have modifications
        $existingInstances = EventInstance::where('recurring_event_id', $recurringEvent->id)
            ->whereBetween('instance_start', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($instance) => $instance->instance_start->format('Y-m-d H:i'));

        // Create virtual instances for each valid occurrence
        return $validOccurrences->map(function ($occurrence) use ($existingInstances) {
            $startKey = $occurrence['start']->format('Y-m-d H:i');
            $existingInstance = $existingInstances->get($startKey);

            // If an instance exists with modifications, use it
            if ($existingInstance && ($existingInstance->overridden_title || $existingInstance->overridden_description || $existingInstance->overridden_location || $existingInstance->cancelled)) {
                // Create a virtual event object from the instance
                $virtualEvent = $this->replicate();
                $virtualEvent->id = $this->id.'-'.$occurrence['start']->format('YmdHis');
                $virtualEvent->start_datetime = $existingInstance->instance_start;
                $virtualEvent->end_datetime = $existingInstance->instance_end;
                $virtualEvent->status = $existingInstance->status ?? $this->status;
                $virtualEvent->title = $existingInstance->overridden_title ?? $this->title;
                $virtualEvent->description = $existingInstance->overridden_description ?? $this->description;
                $virtualEvent->completed_at = $existingInstance->completed_at;
                $virtualEvent->is_instance = true;
                $virtualEvent->instance_start = $occurrence['start'];
                $virtualEvent->instance_end = $occurrence['end'];
                $virtualEvent->cancelled = $existingInstance->cancelled ?? false;

                return $virtualEvent;
            }

            // Create a virtual event object for this occurrence
            $virtualEvent = $this->replicate();
            $virtualEvent->id = $this->id.'-'.$occurrence['start']->format('YmdHis');
            $virtualEvent->start_datetime = $occurrence['start'];
            $virtualEvent->end_datetime = $occurrence['end'];
            $virtualEvent->is_instance = true;
            $virtualEvent->instance_start = $occurrence['start'];
            $virtualEvent->instance_end = $occurrence['end'];

            return $virtualEvent;
        });
    }
}

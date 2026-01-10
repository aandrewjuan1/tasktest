<?php

namespace App\Models;

use App\Enums\CollaborationPermission;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'complexity',
        'duration',
        'start_datetime',
        'end_datetime',
        'project_id',
        'event_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'complexity' => TaskComplexity::class,
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'completed_at' => 'datetime',
            'duration' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            // Set defaults if null
            if (is_null($task->status)) {
                $task->status = TaskStatus::ToDo;
            }
            if (is_null($task->priority)) {
                $task->priority = TaskPriority::Medium;
            }
            if (is_null($task->complexity)) {
                $task->complexity = TaskComplexity::Moderate;
            }
            if (is_null($task->duration)) {
                $task->duration = 60;
            }
            // Dates can be null - no default assignment
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function recurringTask(): HasOne
    {
        return $this->hasOne(RecurringTask::class);
    }

    public function pomodoroSessions(): HasMany
    {
        return $this->hasMany(PomodoroSession::class);
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

    public function getCollaboratorPermission(User $user): ?CollaborationPermission
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

    public function scopeFilterByPriority($query, ?string $priority): Builder
    {
        if ($priority && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        return $query;
    }

    public function scopeFilterByStatus($query, ?string $status): Builder
    {
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }

    public function scopeFilterByComplexity($query, ?string $complexity): Builder
    {
        if ($complexity && $complexity !== 'all') {
            $query->where('complexity', $complexity);
        }

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

    public function scopeSortByPriority($query, string $direction = 'desc'): Builder
    {
        return $query->orderByRaw("CASE
            WHEN priority = 'urgent' THEN 4
            WHEN priority = 'high' THEN 3
            WHEN priority = 'medium' THEN 2
            WHEN priority = 'low' THEN 1
            ELSE 0
        END {$direction}");
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
            'priority' => $query->orderByRaw("CASE
                WHEN priority = 'urgent' THEN 4
                WHEN priority = 'high' THEN 3
                WHEN priority = 'medium' THEN 2
                WHEN priority = 'low' THEN 1
                ELSE 0
            END {$direction}"),
            'created_at' => $query->orderBy('created_at', $direction),
            'start_datetime' => $query->orderBy('start_datetime', $direction),
            'end_datetime' => $query->orderBy('end_datetime', $direction),
            'title' => $query->orderBy('title', $direction),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderBy('created_at', 'desc'),
        };
    }
}

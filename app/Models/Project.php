<?php

namespace App\Models;

use App\Enums\CollaborationPermission;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            // Set default start_date to current date if not provided
            if (is_null($project->start_date)) {
                $project->start_date = \Illuminate\Support\Carbon::today();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
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
        // Projects don't have priority, so this is a no-op for interface consistency
        return $query;
    }

    public function scopeFilterByStatus($query, ?string $status): Builder
    {
        // Projects don't have status, so this is a no-op for interface consistency
        return $query;
    }

    public function scopeDateFilter($query, ?Carbon $date): Builder
    {
        if (!$date) {
            return $query;
        }

        $targetDate = $date->format('Y-m-d');

        return $query->where(function ($q) use ($targetDate) {
            $q->where(function ($dateQ) use ($targetDate) {
                $dateQ->whereNotNull('start_date')
                    ->where(function ($subQ) use ($targetDate) {
                        $subQ->whereDate('start_date', $targetDate)
                            ->orWhereDate('end_date', $targetDate)
                            ->orWhere(function ($spanQ) use ($targetDate) {
                                $spanQ->whereDate('start_date', '<=', $targetDate)
                                    ->whereDate('end_date', '>=', $targetDate);
                            });
                    });
            });
        });
    }

    public function scopeSortByCreatedAt($query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('created_at', $direction);
    }

    public function scopeSortByStartDate($query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('start_date', $direction);
    }

    public function scopeSortByEndDate($query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('end_date', $direction);
    }

    public function scopeSortByName($query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('name', $direction);
    }

    public function scopeOrderByField($query, ?string $field, string $direction = 'asc'): Builder
    {
        if (!$field) {
            return $query->orderBy('created_at', 'desc');
        }

        return match ($field) {
            'created_at' => $query->orderBy('created_at', $direction),
            'start_datetime' => $query->orderBy('start_date', $direction), // Map to start_date for projects
            'end_datetime' => $query->orderBy('end_date', $direction), // Map to end_date for projects
            'title' => $query->orderBy('name', $direction), // Map to name for projects
            'status' => $query->orderBy('created_at', 'desc'), // Projects don't have status
            'priority' => $query->orderBy('created_at', 'desc'), // Projects don't have priority
            default => $query->orderBy('created_at', 'desc'),
        };
    }
}

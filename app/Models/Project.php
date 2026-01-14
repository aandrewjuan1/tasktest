<?php

namespace App\Models;

use App\Enums\CollaborationPermission;
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
        'start_datetime',
        'end_datetime',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            // Dates can be null - no default assignment
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
}

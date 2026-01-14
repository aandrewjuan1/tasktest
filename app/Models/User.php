<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'workos_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'workos_id',
        'remember_token',
    ];

    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * Tasks owned by the user.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Projects owned by the user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Events owned by the user.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Pomodoro sessions for the user.
     */
    public function pomodoroSessions(): HasMany
    {
        return $this->hasMany(PomodoroSession::class);
    }

    /**
     * Reminders for the user.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * Notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Pomodoro settings for the user.
     */
    public function pomodoroSettings(): HasOne
    {
        return $this->hasOne(PomodoroSettings::class);
    }

    /**
     * Notification preferences for the user.
     */
    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Collaborations where user is a collaborator.
     */
    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Tasks user collaborates on.
     */
    public function collaboratedTasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'collaboratable', 'collaborations')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /**
     * Events user collaborates on.
     */
    public function collaboratedEvents(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'collaboratable', 'collaborations')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /**
     * Projects user collaborates on.
     */
    public function collaboratedProjects(): MorphToMany
    {
        return $this->morphedByMany(Project::class, 'collaboratable', 'collaborations')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

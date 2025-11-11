<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Supported task statuses.
     *
     * @var list<string>
     */
    public const STATUSES = ['to-do', 'in-progress', 'completed'];

    /**
     * Supported priority levels.
     *
     * @var list<string>
     */
    public const PRIORITIES = ['low', 'medium', 'high'];

    /**
     * Supported task types.
     *
     * @var list<string>
     */
    public const TYPES = ['assignment', 'quiz', 'exam', 'project', 'other'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'subject',
        'type',
        'priority',
        'status',
        'deadline',
        'estimated_minutes',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_minutes' => 'integer',
        ];
    }

    /**
     * Scope the query to the given user.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    /**
     * Task belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

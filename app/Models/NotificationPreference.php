<?php

namespace App\Models;

use App\Enums\NotificationFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'reminder_notifications_enabled',
        'task_due_notifications_enabled',
        'event_start_notifications_enabled',
        'pomodoro_notifications_enabled',
        'achievement_notifications_enabled',
        'system_notifications_enabled',
        'in_app_enabled',
        'email_enabled',
        'push_enabled',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'notification_frequency',
    ];

    protected function casts(): array
    {
        return [
            'notification_frequency' => NotificationFrequency::class,
            'reminder_notifications_enabled' => 'boolean',
            'task_due_notifications_enabled' => 'boolean',
            'event_start_notifications_enabled' => 'boolean',
            'pomodoro_notifications_enabled' => 'boolean',
            'achievement_notifications_enabled' => 'boolean',
            'system_notifications_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

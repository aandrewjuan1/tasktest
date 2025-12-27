<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimegridSetting extends Model
{
    protected $fillable = [
        'user_id',
        'start_hour',
        'end_hour',
        'hour_height',
        'show_weekends',
        'default_event_duration',
        'slot_increment',
    ];

    protected function casts(): array
    {
        return [
            'show_weekends' => 'boolean',
            'start_hour' => 'integer',
            'end_hour' => 'integer',
            'hour_height' => 'integer',
            'default_event_duration' => 'integer',
            'slot_increment' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AssistantInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'interaction_type',
        'entity_type',
        'entity_id',
        'prompt_snapshot',
        'response_data',
        'reasoning_snippet',
        'model_used',
        'tokens_used',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'prompt_snapshot' => 'array',
            'response_data' => 'array',
            'tokens_used' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function toolExecutions(): HasMany
    {
        return $this->hasMany(AssistantToolExecution::class, 'interaction_id');
    }

    public function feedback(): MorphMany
    {
        return $this->morphMany(AssistantFeedback::class, 'feedbackable');
    }
}

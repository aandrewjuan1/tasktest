<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AssistantFeedback extends Model
{
    use HasFactory;

    protected $table = 'assistant_feedback';

    protected $fillable = [
        'user_id',
        'feedbackable_type',
        'feedbackable_id',
        'rating',
        'feedback_text',
        'improvement_suggestion',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feedbackable(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantToolExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'interaction_id',
        'tool_name',
        'input_parameters',
        'output_result',
        'execution_status',
        'error_message',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_parameters' => 'array',
            'output_result' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'message_id');
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(AssistantInteraction::class, 'interaction_id');
    }
}

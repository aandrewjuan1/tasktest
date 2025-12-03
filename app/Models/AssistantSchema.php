<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistantSchema extends Model
{
    use HasFactory;

    protected $fillable = [
        'schema_name',
        'schema_type',
        'json_schema',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'json_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

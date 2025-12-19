<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Collaboration extends Model
{
    use HasFactory;

    public const PERMISSION_VIEW = 'view';

    public const PERMISSION_COMMENT = 'comment';

    public const PERMISSION_EDIT = 'edit';

    protected $fillable = [
        'collaboratable_type',
        'collaboratable_id',
        'user_id',
        'permission',
    ];

    public function collaboratable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canEdit(): bool
    {
        return $this->permission === self::PERMISSION_EDIT;
    }

    public function canComment(): bool
    {
        return in_array($this->permission, [self::PERMISSION_COMMENT, self::PERMISSION_EDIT]);
    }

    public function canView(): bool
    {
        return true; // All permissions can view
    }

    public function scopeByPermission($query, string $permission)
    {
        return $query->where('permission', $permission);
    }

    public function scopeEditors($query)
    {
        return $query->where('permission', self::PERMISSION_EDIT);
    }

    public function scopeCommenters($query)
    {
        return $query->whereIn('permission', [self::PERMISSION_COMMENT, self::PERMISSION_EDIT]);
    }

    public function scopeViewers($query)
    {
        return $query; // All collaborators are viewers
    }
}

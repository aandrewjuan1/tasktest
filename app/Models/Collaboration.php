<?php

namespace App\Models;

use App\Enums\CollaborationPermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Collaboration extends Model
{
    use HasFactory;

    protected $fillable = [
        'collaboratable_type',
        'collaboratable_id',
        'user_id',
        'permission',
    ];

    protected function casts(): array
    {
        return [
            'permission' => CollaborationPermission::class,
        ];
    }

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
        return $this->permission === CollaborationPermission::Edit;
    }

    public function canComment(): bool
    {
        return in_array($this->permission, [CollaborationPermission::Comment, CollaborationPermission::Edit]);
    }

    public function canView(): bool
    {
        return true; // All permissions can view
    }

    public function scopeByPermission($query, CollaborationPermission $permission)
    {
        return $query->where('permission', $permission);
    }

    public function scopeEditors($query)
    {
        return $query->where('permission', CollaborationPermission::Edit);
    }

    public function scopeCommenters($query)
    {
        return $query->whereIn('permission', [CollaborationPermission::Comment, CollaborationPermission::Edit]);
    }

    public function scopeViewers($query)
    {
        return $query; // All collaborators are viewers
    }
}

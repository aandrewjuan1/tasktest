<?php

namespace App\Services;

use App\Enums\CollaborationPermission;
use App\Models\Collaboration;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CollaborationService
{
    public function addCollaborator(Task $task, User $user, CollaborationPermission $permission): Collaboration
    {
        return DB::transaction(function () use ($task, $user, $permission) {
            return Collaboration::create([
                'collaboratable_type' => Task::class,
                'collaboratable_id' => $task->id,
                'user_id' => $user->id,
                'permission' => $permission,
            ]);
        });
    }

    public function updateCollaboratorPermission(Collaboration $collaboration, CollaborationPermission $permission): void
    {
        DB::transaction(function () use ($collaboration, $permission) {
            $collaboration->update(['permission' => $permission]);
        });
    }

    public function removeCollaborator(Collaboration $collaboration): void
    {
        DB::transaction(function () use ($collaboration) {
            $collaboration->delete();
        });
    }
}

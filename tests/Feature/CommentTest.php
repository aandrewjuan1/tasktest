<?php

declare(strict_types=1);

use App\Enums\CollaborationPermission;
use App\Models\Collaboration;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

it('allows task owner to create a comment', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $comment = Comment::create([
        'task_id' => $task->id,
        'user_id' => $user->id,
        'content' => 'Owner comment',
    ]);

    expect($comment->task_id)->toBe($task->id)
        ->and($comment->user_id)->toBe($user->id)
        ->and($comment->content)->toBe('Owner comment');
});

it('allows collaborator with comment permission to create a comment', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    Collaboration::create([
        'collaboratable_type' => Task::class,
        'collaboratable_id' => $task->id,
        'user_id' => $collaborator->id,
        'permission' => CollaborationPermission::Comment,
    ]);

    $comment = Comment::create([
        'task_id' => $task->id,
        'user_id' => $collaborator->id,
        'content' => 'Collaborator comment',
    ]);

    expect($comment->task_id)->toBe($task->id)
        ->and($comment->user_id)->toBe($collaborator->id);
});

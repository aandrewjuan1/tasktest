<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;

it('allows tags to attach to tasks, events, and projects', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $task = Task::factory()->for($user)->create();
    $project = Project::factory()->for($user)->create();
    $event = Event::create([
        'user_id' => $user->id,
        'title' => 'Planning Session',
        'description' => 'Quarterly planning',
        'start_datetime' => now(),
        'end_datetime' => now()->addHour(),
        'all_day' => false,
        'timezone' => 'UTC',
        'location' => 'Conference Room',
        'color' => '#FF0000',
        'status' => 'scheduled',
    ]);

    $task->tags()->attach($tag);
    $event->tags()->attach($tag);
    $project->tags()->attach($tag);

    expect($tag->tasks)->toHaveCount(1);
    expect($tag->events)->toHaveCount(1);
    expect($tag->projects)->toHaveCount(1);
});

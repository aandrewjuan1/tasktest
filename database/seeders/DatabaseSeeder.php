<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Find or create the specified user
        $user = User::firstOrCreate(
            ['email' => 'andrew.juan.cvt@eac.edu.ph'],
            [
                'name' => 'Andrew Juan',
                'email_verified_at' => now(),
                'workos_id' => 'fake-'.str()->random(10),
                'avatar' => '',
            ]
        );

        // Create tags (10-15 tags)
        $tags = Tag::factory(12)->create();

        // Create projects for the user (5-10 projects)
        $projects = Project::factory(8)->create([
            'user_id' => $user->id,
        ]);

        // Create events for the user (5-10 events)
        $events = Event::factory(7)->create([
            'user_id' => $user->id,
        ]);

        // Create tasks for the user (10-20 tasks)
        $tasks = Task::factory(15)->create([
            'user_id' => $user->id,
        ]);

        // Associate some tasks with projects
        $tasksWithProjects = $tasks->random(min(10, $tasks->count()));
        foreach ($tasksWithProjects as $task) {
            $task->update([
                'project_id' => $projects->random()->id,
            ]);
        }

        // Associate some tasks with events
        $tasksWithEvents = $tasks->random(min(8, $tasks->count()));
        foreach ($tasksWithEvents as $task) {
            $task->update([
                'event_id' => $events->random()->id,
            ]);
        }

        // Attach tags to tasks, events, and projects
        foreach ($tasks as $task) {
            $task->tags()->attach($tags->random(rand(1, 3)));
        }

        foreach ($events as $event) {
            $event->tags()->attach($tags->random(rand(1, 3)));
        }

        foreach ($projects as $project) {
            $project->tags()->attach($tags->random(rand(1, 4)));
        }
    }
}

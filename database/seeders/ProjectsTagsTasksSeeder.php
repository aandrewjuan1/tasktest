<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectsTagsTasksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the user by email
        $user = User::where('email', 'andrew.juan.cvt@eac.edu.ph')->first();

        if (! $user) {
            $this->command->error('User with email andrew.juan.cvt@eac.edu.ph not found!');

            return;
        }

        $this->command->info("Seeding data for user: {$user->name} ({$user->email})");

        // Create tags first
        $this->command->info('Creating tags...');
        $tags = collect([
            'Frontend',
            'Backend',
            'Database',
            'Design',
            'Testing',
            'Documentation',
            'Bug',
            'Feature',
            'Enhancement',
            'Research',
        ])->map(function ($tagName) {
            return Tag::firstOrCreate(['name' => $tagName]);
        });

        $this->command->info("Created {$tags->count()} tags");

        // Create projects for the user
        $this->command->info('Creating projects...');
        $projects = Project::factory()
            ->count(5)
            ->for($user)
            ->create();

        $this->command->info("Created {$projects->count()} projects");

        foreach ($projects as $project) {
            $project->tags()->attach(
                $tags->random(rand(1, 3))->pluck('id')
            );
        }

        // Create tasks for the user
        $this->command->info('Creating tasks...');

        $taskCount = 0;

        // Create tasks with different statuses and priorities
        foreach ($projects as $project) {
            // Create 3-5 tasks per project
            $tasksForProject = Task::factory()
                ->count(rand(3, 5))
                ->for($user)
                ->for($project)
                ->create();

            // Attach random tags to each task (1-3 tags per task)
            foreach ($tasksForProject as $task) {
                $task->tags()->attach(
                    $tags->random(rand(1, 3))->pluck('id')
                );
            }

            $taskCount += $tasksForProject->count();
        }

        // Create some tasks without projects (personal tasks)
        $personalTasks = Task::factory()
            ->count(5)
            ->for($user)
            ->create();

        foreach ($personalTasks as $task) {
            $task->tags()->attach(
                $tags->random(rand(1, 2))->pluck('id')
            );
        }

        $taskCount += $personalTasks->count();

        $this->command->info("Created {$taskCount} tasks");
        $this->command->info('Seeding completed successfully!');
    }
}

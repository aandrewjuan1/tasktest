<?php

namespace Database\Seeders;

use App\Enums\RecurrenceType;
use App\Models\Event;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Find the specified user (must be registered before running seeder)
        $user = User::where('email', 'andrew.juan.cvt@eac.edu.ph')->first();

        if (! $user) {
            $this->command->warn('User andrew.juan.cvt@eac.edu.ph not found. Please register this user before running the seeder.');
            return;
        }

        // Create thesis project
        $project = Project::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create birthday event
        $event = Event::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create daily repeating tasks with RecurringTask records
        $tasks = [
            Task::factory()->makeCoffee()->create(['user_id' => $user->id]),
            Task::factory()->readBook()->create(['user_id' => $user->id]),
            Task::factory()->drawing()->create(['user_id' => $user->id]),
            Task::factory()->goForWalk()->create(['user_id' => $user->id]),
            Task::factory()->studySmth()->create(['user_id' => $user->id]),
            Task::factory()->dayTradingForex()->create(['user_id' => $user->id]),
        ];

        // Create RecurringTask records for each task
        foreach ($tasks as $task) {
            // Day trading forex is weekly (Monday-Friday), others are daily
            if ($task->title === 'day trading forex') {
                RecurringTask::create([
                    'task_id' => $task->id,
                    'recurrence_type' => RecurrenceType::Weekly,
                    'interval' => 1,
                    'start_datetime' => $task->start_datetime ?? Carbon::today(),
                    'end_datetime' => $task->end_datetime,
                    'days_of_week' => '1,2,3,4,5', // Monday-Friday (0=Sunday, 1=Monday, etc.)
                ]);
            } else {
                RecurringTask::create([
                    'task_id' => $task->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => Carbon::today(),
                    'end_datetime' => null,
                    'days_of_week' => null,
                ]);
            }
        }
    }
}

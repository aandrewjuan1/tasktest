<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\RecurrenceType;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\RecurringEvent;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AndrewUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        try {
            $user = User::where('email', 'andrew.juan.cvt@eac.edu.ph')->first();

            if (! $user) {
                throw new \Exception('User with email andrew.juan.cvt@eac.edu.ph does not exist. Please create the user first.');
            }

            DB::transaction(function () use ($user) {
                // Create THESIS PROJECT
                $project = Project::create([
                    'user_id' => $user->id,
                    'name' => 'THESIS PROJECT',
                    'description' => 'Final thesis project for graduation',
                    'start_datetime' => Carbon::create(2025, 1, 19, 0, 0, 0),
                    'end_datetime' => Carbon::create(2025, 5, 19, 23, 59, 59),
                ]);

                $today = Carbon::today();

                // 1. 9:00 AM - Wake up and toothbrush (15 mins)
                $task1 = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Wake up and toothbrush',
                    'description' => 'Morning routine: wake up and brush teeth',
                    'status' => TaskStatus::ToDo,
                    'priority' => TaskPriority::Medium,
                    'complexity' => TaskComplexity::Simple,
                    'duration' => 15,
                    'start_datetime' => $today->copy()->setTime(9, 0, 0),
                    'end_datetime' => $today->copy()->setTime(9, 15, 0),
                ]);

                RecurringTask::create([
                    'task_id' => $task1->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => $today->copy()->setTime(9, 0, 0),
                    'end_datetime' => null,
                ]);

                // 2. 9:15 AM - Make coffee (15 mins)
                $task2 = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Make coffee',
                    'description' => 'Prepare morning coffee',
                    'status' => TaskStatus::ToDo,
                    'priority' => TaskPriority::Medium,
                    'complexity' => TaskComplexity::Simple,
                    'duration' => 15,
                    'start_datetime' => $today->copy()->setTime(9, 15, 0),
                    'end_datetime' => $today->copy()->setTime(9, 30, 0),
                ]);

                RecurringTask::create([
                    'task_id' => $task2->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => $today->copy()->setTime(9, 15, 0),
                    'end_datetime' => null,
                ]);

                // 3. 9:30 AM - Read book (30 mins)
                $task3 = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Read book',
                    'description' => 'Daily reading session',
                    'status' => TaskStatus::ToDo,
                    'priority' => TaskPriority::Medium,
                    'complexity' => TaskComplexity::Moderate,
                    'duration' => 30,
                    'start_datetime' => $today->copy()->setTime(9, 30, 0),
                    'end_datetime' => $today->copy()->setTime(10, 0, 0),
                ]);

                RecurringTask::create([
                    'task_id' => $task3->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => $today->copy()->setTime(9, 30, 0),
                    'end_datetime' => null,
                ]);

                // 4. 10:00 AM - Code for thesis (2 hrs = 120 mins) - linked to THESIS PROJECT
                $task4 = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Code for thesis',
                    'description' => 'Work on thesis project coding tasks',
                    'status' => TaskStatus::ToDo,
                    'priority' => TaskPriority::High,
                    'complexity' => TaskComplexity::Complex,
                    'duration' => 120,
                    'start_datetime' => $today->copy()->setTime(10, 0, 0),
                    'end_datetime' => $today->copy()->setTime(12, 0, 0),
                    'project_id' => $project->id,
                ]);

                RecurringTask::create([
                    'task_id' => $task4->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => $today->copy()->setTime(10, 0, 0),
                    'end_datetime' => null,
                ]);

                // 5. 12:00 PM - Lunch (30 mins)
                $task5 = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Lunch',
                    'description' => 'Lunch break',
                    'status' => TaskStatus::ToDo,
                    'priority' => TaskPriority::Medium,
                    'complexity' => TaskComplexity::Simple,
                    'duration' => 30,
                    'start_datetime' => $today->copy()->setTime(12, 0, 0),
                    'end_datetime' => $today->copy()->setTime(12, 30, 0),
                ]);

                RecurringTask::create([
                    'task_id' => $task5->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => $today->copy()->setTime(12, 0, 0),
                    'end_datetime' => null,
                ]);

                // 6. 1:00 PM - Play (2 hrs = 120 mins)
                $task6 = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Play',
                    'description' => 'Recreation and play time',
                    'status' => TaskStatus::ToDo,
                    'priority' => TaskPriority::Low,
                    'complexity' => TaskComplexity::Moderate,
                    'duration' => 120,
                    'start_datetime' => $today->copy()->setTime(13, 0, 0),
                    'end_datetime' => $today->copy()->setTime(15, 0, 0),
                ]);

                RecurringTask::create([
                    'task_id' => $task6->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => $today->copy()->setTime(13, 0, 0),
                    'end_datetime' => null,
                ]);

                // 7. 3:00 PM - Day trade forex (8 hrs = 480 mins, ends at 11:00 PM)
                $task7 = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Day trade forex',
                    'description' => 'Forex trading session',
                    'status' => TaskStatus::ToDo,
                    'priority' => TaskPriority::High,
                    'complexity' => TaskComplexity::Complex,
                    'duration' => 480,
                    'start_datetime' => $today->copy()->setTime(15, 0, 0),
                    'end_datetime' => $today->copy()->setTime(23, 0, 0),
                ]);

                RecurringTask::create([
                    'task_id' => $task7->id,
                    'recurrence_type' => RecurrenceType::Daily,
                    'interval' => 1,
                    'start_datetime' => $today->copy()->setTime(15, 0, 0),
                    'end_datetime' => null,
                ]);

                // Create yearly events
                // 1. January 31 - Birthday (yearly)
                $birthdayEvent = Event::create([
                    'user_id' => $user->id,
                    'title' => 'My Birthday',
                    'description' => 'Birthday celebration',
                    'status' => EventStatus::Scheduled,
                    'start_datetime' => Carbon::create(2025, 1, 31, 0, 0, 0),
                    'end_datetime' => Carbon::create(2025, 1, 31, 23, 59, 59),
                ]);

                RecurringEvent::create([
                    'event_id' => $birthdayEvent->id,
                    'recurrence_type' => RecurrenceType::Yearly,
                    'interval' => 1,
                    'start_datetime' => Carbon::create(2025, 1, 31, 0, 0, 0),
                    'end_datetime' => null,
                ]);

                // 2. June 16 - Girlfriend's Birthday (yearly)
                $gfBirthdayEvent = Event::create([
                    'user_id' => $user->id,
                    'title' => "Girlfriend's Birthday",
                    'description' => "Girlfriend's birthday celebration",
                    'status' => EventStatus::Scheduled,
                    'start_datetime' => Carbon::create(2025, 6, 16, 0, 0, 0),
                    'end_datetime' => Carbon::create(2025, 6, 16, 23, 59, 59),
                ]);

                RecurringEvent::create([
                    'event_id' => $gfBirthdayEvent->id,
                    'recurrence_type' => RecurrenceType::Yearly,
                    'interval' => 1,
                    'start_datetime' => Carbon::create(2025, 6, 16, 0, 0, 0),
                    'end_datetime' => null,
                ]);
            });
        } catch (\Exception $e) {
            $this->command->error('Error seeding Andrew user data: '.$e->getMessage());
            throw $e;
        }
    }
}

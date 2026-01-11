<?php

namespace Database\Factories;

use App\Enums\RecurrenceType;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTask>
 */
class RecurringTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'recurrence_type' => RecurrenceType::Daily,
            'interval' => 1,
            'start_datetime' => now(),
            'end_datetime' => null,
            'days_of_week' => null,
        ];
    }

    /**
     * Configure the factory to copy datetime from the task after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($recurringTask) {
            $task = $recurringTask->task;
            if ($task) {
                $recurringTask->update([
                    'start_datetime' => $task->start_datetime ?? now(),
                    'end_datetime' => $task->end_datetime,
                ]);
            }
        });
    }
}

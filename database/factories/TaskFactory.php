<?php

namespace Database\Factories;

use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Start datetime is randomly set to be beyond today (anywhere from tomorrow to 6 months in the future)
        $minStart = now()->addDay()->startOfDay();
        $maxStart = now()->addMonths(6)->endOfDay();
        $startDatetime = Carbon::createFromTimestamp(
            fake()->numberBetween($minStart->timestamp, $maxStart->timestamp)
        );
        // End datetime is randomly set to be after start datetime (anywhere from 1 hour to 3 months later)
        $minEnd = $startDatetime->copy()->addHour();
        $maxEnd = $startDatetime->copy()->addMonths(3);
        $endDatetime = Carbon::createFromTimestamp(
            fake()->numberBetween($minEnd->timestamp, $maxEnd->timestamp)
        );
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'complexity' => fake()->randomElement(TaskComplexity::cases()),
            'duration' => fake()->optional()->numberBetween(15, 480), // 15 minutes to 8 hours
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'project_id' => null,
            'event_id' => null,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ];
    }
}

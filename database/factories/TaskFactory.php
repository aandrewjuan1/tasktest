<?php

namespace Database\Factories;

use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\User;
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
        $startDatetime = fake()->dateTimeBetween('now', '+1 month');
        $endDatetime = fake()->dateTimeBetween($startDatetime, '+2 months');
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

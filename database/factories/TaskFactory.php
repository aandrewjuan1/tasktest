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
        return [
            'user_id' => User::factory(),
            'title' => 'make coffee',
            'description' => null,
            'status' => TaskStatus::ToDo,
            'priority' => TaskPriority::Low,
            'complexity' => TaskComplexity::Simple,
            'duration' => null,
            'start_datetime' => null,
            'end_datetime' => null,
            'project_id' => null,
            'event_id' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the task is "make coffee".
     */
    public function makeCoffee(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'make coffee',
            'description' => null,
            'status' => TaskStatus::ToDo,
            'priority' => TaskPriority::Low,
            'complexity' => TaskComplexity::Simple,
            'duration' => null,
            'start_datetime' => null,
            'end_datetime' => null,
        ]);
    }

    /**
     * Indicate that the task is "read a book".
     */
    public function readBook(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'read a book',
            'description' => null,
            'status' => TaskStatus::ToDo,
            'priority' => TaskPriority::Medium,
            'complexity' => TaskComplexity::Simple,
            'duration' => null,
            'start_datetime' => null,
            'end_datetime' => null,
        ]);
    }

    /**
     * Indicate that the task is "drawing".
     */
    public function drawing(): static
    {
        $today = Carbon::today();
        $startDatetime = $today->copy()->setTime(22, 0, 0); // 10pm

        return $this->state(fn (array $attributes) => [
            'title' => 'drawing',
            'description' => null,
            'status' => TaskStatus::ToDo,
            'priority' => TaskPriority::Medium,
            'complexity' => TaskComplexity::Moderate,
            'duration' => 60, // 1 hour
            'start_datetime' => $startDatetime,
            'end_datetime' => null,
        ]);
    }

    /**
     * Indicate that the task is "go for a walk".
     */
    public function goForWalk(): static
    {
        $today = Carbon::today();
        $startDatetime = $today->copy()->setTime(17, 0, 0); // 5pm

        return $this->state(fn (array $attributes) => [
            'title' => 'go for a walk',
            'description' => null,
            'status' => TaskStatus::ToDo,
            'priority' => TaskPriority::Medium,
            'complexity' => TaskComplexity::Simple,
            'duration' => 120, // 2 hours
            'start_datetime' => $startDatetime,
            'end_datetime' => null,
        ]);
    }

    /**
     * Indicate that the task is "study smth".
     */
    public function studySmth(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'study smth',
            'description' => null,
            'status' => TaskStatus::ToDo,
            'priority' => TaskPriority::High,
            'complexity' => TaskComplexity::Moderate,
            'duration' => null,
            'start_datetime' => null,
            'end_datetime' => null,
        ]);
    }
}

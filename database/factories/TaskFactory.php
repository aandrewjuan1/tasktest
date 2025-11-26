<?php

namespace Database\Factories;

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
        $startDate = fake()->dateTimeBetween('now', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+2 months');

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['to_do', 'doing', 'done']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'complexity' => fake()->randomElement(['simple', 'moderate', 'complex']),
            'duration' => fake()->numberBetween(15, 240),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'doing',
        ]);
    }

    /**
     * Indicate that the task is urgent priority.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Task;
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
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'subject' => $this->faker->optional()->word(),
            'type' => $this->faker->randomElement(Task::TYPES),
            'priority' => $this->faker->randomElement(Task::PRIORITIES),
            'status' => $this->faker->randomElement(Task::STATUSES),
            'deadline' => $this->faker->optional()->dateTimeBetween('+1 day', '+1 month'),
            'estimated_minutes' => $this->faker->optional()->numberBetween(30, 240),
            'completed_at' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
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

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'all_day' => fake()->boolean(20),
            'timezone' => fake()->timezone(),
            'location' => fake()->optional()->address(),
            'color' => fake()->optional()->hexColor(),
            'status' => fake()->randomElement(['scheduled', 'cancelled', 'completed', 'tentative']),
        ];
    }
}

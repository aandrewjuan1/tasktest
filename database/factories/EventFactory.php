<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
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
        $allDay = fake()->boolean(30); // 30% chance of all-day event
        $startDateTime = fake()->dateTimeBetween('now', '+2 months');
        $endDateTime = fake()->dateTimeBetween($startDateTime, $startDateTime->format('Y-m-d H:i:s').' +4 hours');

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'all_day' => $allDay,
            'timezone' => config('app.timezone'),
            'location' => fake()->optional()->address(),
            'color' => fake()->optional()->hexColor(),
            'status' => fake()->randomElement(EventStatus::cases()),
        ];
    }
}

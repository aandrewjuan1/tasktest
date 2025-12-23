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
        $startDateTime = fake()->dateTimeBetween('-1 month', '+2 months');
        $endDateTime = $allDay
            ? fake()->dateTimeBetween($startDateTime, $startDateTime->format('Y-m-d').' 23:59:59')
            : fake()->dateTimeBetween($startDateTime, '+1 day');
        $timezone = fake()->timezone();

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'all_day' => $allDay,
            'timezone' => $timezone,
            'location' => fake()->optional()->address(),
            'color' => fake()->optional()->hexColor(),
            'status' => fake()->randomElement(EventStatus::cases()),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
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
        // Start datetime is based on creation time (now)
        $startDateTime = now();
        // End datetime is randomly set to be after start datetime
        // For all-day events, end can be up to 7 days later; for timed events, up to 8 hours later
        $minEnd = $startDateTime->copy()->addMinutes(30);
        $maxEnd = $allDay
            ? $startDateTime->copy()->addDays(7)
            : $startDateTime->copy()->addHours(8);
        $endDateTime = Carbon::createFromTimestamp(
            fake()->numberBetween($minEnd->timestamp, $maxEnd->timestamp)
        );

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

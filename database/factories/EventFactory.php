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
        $startDateTime = Carbon::create(2026, 1, 31)->startOfDay();
        $endDateTime = $startDateTime->copy()->endOfDay();

        return [
            'user_id' => User::factory(),
            'title' => 'my bday',
            'description' => null,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'status' => EventStatus::Scheduled,
        ];
    }
}

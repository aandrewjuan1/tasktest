<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDatetime = Carbon::create(2026, 1, 1)->startOfDay();
        $endDatetime = Carbon::create(2026, 5, 12)->endOfDay();

        return [
            'user_id' => User::factory(),
            'name' => 'thesis',
            'description' => null,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
        ];
    }
}

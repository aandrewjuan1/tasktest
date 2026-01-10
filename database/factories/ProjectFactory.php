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
        // Start datetime is randomly set to be beyond today (anywhere from tomorrow to 6 months in the future)
        // Or randomly null
        $startDatetime = fake()->boolean(70)
            ? Carbon::createFromTimestamp(
                fake()->numberBetween(
                    now()->addDay()->startOfDay()->timestamp,
                    now()->addMonths(6)->endOfDay()->timestamp
                )
            )
            : null;
        // End datetime is randomly set to be after start datetime (anywhere from 1 week to 12 months later)
        // Or randomly null
        $endDatetime = ($startDatetime !== null && fake()->boolean(70))
            ? Carbon::createFromTimestamp(
                fake()->numberBetween(
                    $startDatetime->copy()->addWeek()->timestamp,
                    $startDatetime->copy()->addMonths(12)->timestamp
                )
            )
            : null;

        $projectNames = [
            'Website Redesign',
            'Mobile App Development',
            'Database Migration',
            'API Integration',
            'E-commerce Platform',
            'Content Management System',
            'Customer Portal',
            'Analytics Dashboard',
            'Marketing Campaign',
            'Product Launch',
            'System Upgrade',
            'Security Audit',
            'Performance Optimization',
            'User Experience Enhancement',
            'Documentation Project',
        ];

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement($projectNames),
            'description' => fake()->optional()->paragraph(),
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
        ];
    }
}

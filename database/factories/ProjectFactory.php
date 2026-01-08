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
        // Start datetime is based on creation time (now)
        $startDatetime = now();
        // End datetime is randomly set to be after start datetime (anywhere from 1 week to 12 months later)
        $minEnd = $startDatetime->copy()->addWeek();
        $maxEnd = $startDatetime->copy()->addMonths(12);
        $endDatetime = Carbon::createFromTimestamp(
            fake()->numberBetween($minEnd->timestamp, $maxEnd->timestamp)
        );

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

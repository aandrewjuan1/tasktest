<?php

namespace Database\Factories;

use App\Models\User;
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
        $startDate = fake()->dateTimeBetween('-6 months', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+6 months');

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
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}

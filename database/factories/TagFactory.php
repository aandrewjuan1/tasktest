<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tagNames = [
            'urgent',
            'homework',
            'project',
            'personal',
            'work',
            'study',
            'meeting',
            'deadline',
            'important',
            'review',
            'development',
            'testing',
            'documentation',
            'design',
            'backend',
            'frontend',
            'api',
            'database',
            'security',
            'maintenance',
        ];

        return [
            'name' => fake()->unique()->randomElement($tagNames),
        ];
    }
}

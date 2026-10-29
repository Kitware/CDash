<?php

namespace Database\Factories;

use App\Models\ProjectMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectMetric>
 */
class ProjectMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Names are limited to 25 characters.
            'name' => Str::random(20),
            'position' => 1,
        ];
    }
}

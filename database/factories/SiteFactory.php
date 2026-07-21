<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Site_' . Str::uuid()->toString(),
            'ip' => fake()->ipv4(),
            'latitude' => substr((string) fake()->latitude(), 0, 10),
            'longitude' => substr((string) fake()->longitude(), 0, 10),
            'outoforder' => false,
        ];
    }
}

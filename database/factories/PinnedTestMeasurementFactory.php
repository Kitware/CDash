<?php

namespace Database\Factories;

use App\Models\PinnedTestMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PinnedTestMeasurement>
 */
class PinnedTestMeasurementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ];
    }
}

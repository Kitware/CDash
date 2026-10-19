<?php

namespace Database\Factories;

use App\Models\TestMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TestMeasurement>
 */
class TestMeasurementFactory extends Factory
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
            'type' => 'text/string',
            'value' => Str::uuid()->toString(),
        ];
    }
}

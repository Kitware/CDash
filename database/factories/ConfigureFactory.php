<?php

namespace Database\Factories;

use App\Models\Configure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Random\RandomException;

/**
 * @extends Factory<Configure>
 */
class ConfigureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     *
     * @throws RandomException
     */
    public function definition(): array
    {
        return [
            'command' => Str::uuid()->toString(),
            'log' => Str::uuid()->toString(),
            'status' => 0,
            'warnings' => 0,
            'crc32' => random_int(0, 1000000),
        ];
    }

    public function withWarnings(int $numWarnings): static
    {
        return $this->state(fn (array $attributes) => [
            'warnings' => $numWarnings,
        ]);
    }
}

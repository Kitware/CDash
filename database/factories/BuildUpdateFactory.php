<?php

namespace Database\Factories;

use App\Models\BuildUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<BuildUpdate>
 */
class BuildUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starttime' => Carbon::createFromInterface(fake()->dateTime()),
            'endtime' => Carbon::createFromInterface(fake()->dateTime()),
            'command' => fake()->text(),
            'type' => 'GIT',
            'status' => '',
            'nfiles' => fake()->numberBetween(0, 1000),
            'warnings' => fake()->numberBetween(0, 1000),
            'revision' => fake()->sha1(),
            'priorrevision' => fake()->sha1(),
            'path' => Str::uuid()->toString(),
        ];
    }
}

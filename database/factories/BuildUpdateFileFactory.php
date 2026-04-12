<?php

namespace Database\Factories;

use App\Models\BuildUpdateFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<BuildUpdateFile>
 */
class BuildUpdateFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filename' => Str::uuid()->toString(),
            'checkindate' => Carbon::createFromInterface(fake()->dateTime()),
            'author' => fake()->name(),
            'email' => fake()->safeEmail(),
            'committer' => fake()->name(),
            'committeremail' => fake()->safeEmail(),
            'log' => fake()->text(),
            'revision' => fake()->sha1(),
            'priorrevision' => fake()->sha1(),
            'status' => 'UPDATED',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\BuildError;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BuildError>
 */
class BuildErrorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sourcefile' => Str::uuid()->toString(),
            'newstatus' => 0,
            'type' => 0,
            'stdoutput' => Str::uuid()->toString(),
            'stderror' => Str::uuid()->toString(),
        ];
    }
}

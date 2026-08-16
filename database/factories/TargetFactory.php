<?php

namespace Database\Factories;

use App\Enums\TargetType;
use App\Models\Target;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Target>
 */
class TargetFactory extends Factory
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
            'type' => TargetType::UNKNOWN,
        ];
    }
}

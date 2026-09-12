<?php

namespace Database\Factories;

use App\Models\BuildGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<BuildGroup>
 */
class BuildGroupFactory extends Factory
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
            'starttime' => Carbon::create(1980),
            'endtime' => null,
            'autoremovetimeframe' => 0,
            'description' => Str::uuid()->toString(),
            'summaryemail' => 0,
            'includesubprojectotal' => 1,
            'emailcommitters' => 0,
            'type' => 'Daily',
        ];
    }
}

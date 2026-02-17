<?php

namespace Database\Factories;

use App\Models\DynamicAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DynamicAnalysis>
 */
class DynamicAnalysisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'passed',
            'checker' => Str::uuid()->toString(),
            'name' => Str::uuid()->toString(),
            'path' => Str::uuid()->toString(),
            'fullcommandline' => Str::uuid()->toString(),
            'log' => Str::uuid()->toString(),
        ];
    }
}

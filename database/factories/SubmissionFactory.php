<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'Build',
            'path' => 'inbox/' . Str::uuid()->toString() . '.xml',
            'status' => SubmissionStatus::QUEUED,
            'received_at' => now('UTC'),
            'next_attempt_at' => now('UTC'),
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::DONE,
            'attempts' => 1,
            'started_at' => now('UTC'),
            'finished_at' => now('UTC'),
        ]);
    }
}

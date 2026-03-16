<?php

namespace Database\Factories;

use App\Models\AuthToken;
use App\Utils\AuthTokenUtil;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuthToken>
 */
class AuthTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created' => Carbon::now(),
            'expires' => Carbon::now()->addYear(),
            'description' => Str::uuid()->toString(),
            'scope' => AuthToken::SCOPE_FULL_ACCESS,
            'hash' => AuthTokenUtil::hashToken(Str::uuid()->toString()),
        ];
    }
}

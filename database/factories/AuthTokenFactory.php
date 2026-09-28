<?php

namespace Database\Factories;

use App\Enums\AuthTokenScope;
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
            'scope' => AuthTokenScope::FULL_ACCESS,
            'hash' => AuthTokenUtil::hashToken(Str::uuid()->toString()),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => Str::uuid()->toString(),
            'lastname' => Str::uuid()->toString(),
            'email' => Str::uuid()->toString() . '@example.com',
            'email_verified_at' => now(),
            'password' => Str::uuid()->toString(),
            'institution' => Str::uuid()->toString(),
            'remember_token' => Str::random(10),
            'admin' => false,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if ($user->password && Hash::needsRehash($user->password)) {
                $user->password = Hash::make($user->password);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function adminUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'admin' => true,
        ]);
    }
}

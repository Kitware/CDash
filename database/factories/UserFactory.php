<?php

use App\Models\User;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(User::class, fn (Faker $faker) => [
    'firstname' => $faker->firstName,
    'lastname' => $faker->lastName,
    'email' => $faker->unique()->safeEmail,
    'email_verified_at' => now(),
    'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
    'remember_token' => Str::random(10),
    'institution' => $faker->company,
]);

$factory->state(User::class, 'admin', ['admin' => 1]);

$factory->afterCreating(User::class, function ($user, $faker) {
    $user->passwords()->insert([
        'userid' => $user->id,
        'date' => now(),
        'password' => $user->password,
    ]);
});

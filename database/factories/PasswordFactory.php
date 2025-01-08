<?php

use App\Password;
use Faker\Generator as Faker;

$factory->define(Password::class, function (Faker $faker) use ($factory) {
    $user = $factory->create('App\Models\User');
    return [
        'userid' => $user->id,
        'password' => $user->password,
        'date' => now(),
    ];
});

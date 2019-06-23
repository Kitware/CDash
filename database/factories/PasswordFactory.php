<?php

use Faker\Generator as Faker;

$factory->define(App\Password::class, function (Faker $faker) use ($factory) {
    $user = $factory->create('App\User');
    return [
        'userid' => $user->id,
        'password' => $user->password,
        'date' => now(),
    ];
});

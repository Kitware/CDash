<?php

use CDash\Middleware\OAuth2\Google;

return [
    'google' => [
        'clientId' => env('GOOGLE_CLIENT_ID'),
        'clientSecret' => env('GOOGLE_CLIENT_SECRET'),
        'hostedDomain' => '*',
        'className' => Google::class,
        'enable' => env('GOOGLE_ENABLE', false),
    ],
];

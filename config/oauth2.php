<?php

use CDash\Middleware\OAuth2\GitHub;
use CDash\Middleware\OAuth2\GitLab;
use CDash\Middleware\OAuth2\Google;

return [
    'github' => [
        'clientId' => env('GITHUB_CLIENT_ID'),
        'clientSecret' => env('GITHUB_CLIENT_SECRET'),
        'className' => GitHub::class,
        'enable' => false,
    ],
    'gitlab' => [
        'clientId' => env('GITLAB_CLIENT_ID'),
        'clientSecret' => env('GITLAB_CLIENT_SECRET'),
        'domain' => env('GITLAB_DOMAIN', 'https://gitlab.com'),
        'className' => GitLab::class,
        'enable' => false,
    ],
    'google' => [
        'clientId' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'clientSecret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'hostedDomain' => '*',
        'className' => Google::class,
        'enable' => false,
    ]
];

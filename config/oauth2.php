<?php

use CDash\Middleware\OAuth2\GitHub;
use CDash\Middleware\OAuth2\GitLab;
use CDash\Middleware\OAuth2\Google;
use CDash\Middleware\OAuth2\LCOAuth;

return [
    'github' => [
        'clientId' => env('GITHUB_CLIENT_ID'),
        'clientSecret' => env('GITHUB_CLIENT_SECRET'),
        'className' => GitHub::class,
        'enable' => env('GITHUB_ENABLE', false),
    ],
    'gitlab' => [
        'clientId' => env('GITLAB_CLIENT_ID'),
        'clientSecret' => env('GITLAB_CLIENT_SECRET'),
        'domain' => env('GITLAB_DOMAIN', 'https://gitlab.com'),
        'className' => GitLab::class,
        'enable' => env('GITLAB_ENABLE', false),
    ],
    'google' => [
        'clientId' => env('GOOGLE_CLIENT_ID'),
        'clientSecret' => env('GOOGLE_CLIENT_SECRET'),
        'hostedDomain' => '*',
        'className' => Google::class,
        'enable' => env('GOOGLE_ENABLE', false),
    ],
    'lcoauth' => [
        'clientId' => env('LCOAUTH_CLIENT_ID'),
        'clientSecret' => env('LCOAUTH_CLIENT_SECRET'),
        'urlAuthorize' => env('LCOAUTH_URL_AUTHORIZE'),
        'urlAccessToken' => env('LCOAUTH_URL_ACCESSTOKEN'),
        'urlResourceOwnerDetails' => env('LCOAUTH_URL_RESOURCEOWNERDETAILS'),
        'className' => LCOAuth::class,
        'enable' => env('LCOAUTH_ENABLE', false),
    ]
];

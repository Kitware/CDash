<?php

use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/github/callback',
        'enable' => env('GITHUB_ENABLE', false),
        'oauth' => true,
        'display_name' => env('GITHUB_DISPLAY_NAME', 'GitHub'),
    ],
    'gitlab' => [
        'client_id' => env('GITLAB_CLIENT_ID'),
        'client_secret' => env('GITLAB_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/gitlab/callback',
        'instance_uri' => env('GITLAB_DOMAIN'),
        'enable' => env('GITLAB_ENABLE', false),
        'oauth' => true,
        'display_name' => env('GITLAB_DISPLAY_NAME', 'GitLab'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'hosted_domain' => '*',
        'redirect' => env('APP_URL') . '/auth/google/callback',
        'enable' => env('GOOGLE_ENABLE', false),
        'oauth' => true,
        'display_name' => env('GOOGLE_DISPLAY_NAME', 'Google'),
    ],

    'pingidentity' => [
        'client_id' => env('PINGIDENTITY_CLIENT_ID'),
        'client_secret' => env('PINGIDENTITY_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/pingidentity/callback',
        'instance_uri' => env('PINGIDENTITY_DOMAIN'),
        'auth_endpoint' => env('PINGIDENTITY_AUTH_ENDPOINT', '/as/authorization.oauth2'),
        'token_endpoint' => env('PINGIDENTITY_TOKEN_ENDPOINT', '/as/token.oauth2'),
        'user_endpoint' => env('PINGIDENTITY_USER_ENDPOINT', '/idp/userinfo.openid'),
        'enable' => env('PINGIDENTITY_ENABLE', false),
        'oauth' => true,
        'display_name' => env('PINGIDENTITY_DISPLAY_NAME', 'PingIdentity'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],
];

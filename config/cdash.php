<?php
$cdash_directory_name = env('CDASH_DIRECTORY', 'cdash');
$cdash = realpath(app_path($cdash_directory_name));

// read in all of our cdash config files
if ($cdash) {
    include_once $cdash . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    include_once $cdash . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'version.php';
}

return [
    'directory' => $cdash,
    'password' => [
        'complexity' => 0,
        'count' => 0,
        'min' => 5,
        'expires' => 0,
    ],
    'version' => '3.0.1',
    'registration' => [
        'email' => [
            'verify' => true,
        ]
    ],
    'file' => [
        'path' => [
            'js' => [
                'controllers' => "{$cdash}/public/js/controllers",
                'version' => "{$cdash}/public/build/js/version.js",
            ],
            'custom' => [
                'views' => "{$cdash}/public/local/views",
            ],
            'public' => "{$cdash}/public",
        ],
        // File endpoints are URIs that respond with actual files, and not html
        'endpoints' => [
            'displayImage.php',
            'generateCTestConfig.php',
        ]
    ],
    'allow' => [
        'authenticated_submissions' => false,
    ],
    'login' => [
        'max_attempts' => 5,
        'lockout' => [
            'duration' => 1,
        ],
    ]
];

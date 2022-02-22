<?php
$cdash_directory_name = env('CDASH_DIRECTORY', 'cdash');
$cdash = realpath(app_path($cdash_directory_name));

// read in all of our cdash config files
if ($cdash) {
    include_once $cdash . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    include_once $cdash . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'version.php';
}

$unlimited_projects = json_decode(env('UNLIMITED_PROJECTS', ''), true);
if (!is_array($unlimited_projects)) {
    $unlimited_projects = [];
}

return [
    'directory' => $cdash,
    'password' => [
        'complexity' => env('MINIMUM_PASSWORD_COMPLEXITY', 0),
        'count' => env('PASSWORD_COMPLEXITY_COUNT', 0),
        'min' => env('MINIMUM_PASSWORD_LENGTH', 5),
        'expires' => env('PASSWORD_EXPIRATION', 0),
        'unique' => env('UNIQUE_PASSWORD_COUNT', 0),
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
    'login' => [
        'max_attempts' => env('LOCKOUT_ATTEMPTS', 5),
        'lockout' => [
            'duration' => env('LOCKOUT_LENGTH', 1),
        ],
    ],
    'active_project_days' => env('ACTIVE_PROJECT_DAYS', 7),
    'autoremove_builds' => env('AUTOREMOVE_BUILDS', false),
    'builds_per_project' => env('BUILDS_PER_PROJECT', 0),
    'curl_localhost_prefix' => env('CURL_LOCALHOST_PREFIX', ''),
    'curl_request_localhost' => env('CURL_REQUEST_LOCALHOST', true),
    'daily_updates' => env('DAILY_UPDATES', true),
    'default_google_analytics' => env('DEFAULT_GOOGLE_ANALYTICS', ''),
    'default_project' => env('DEFAULT_PROJECT', null),
    'delete_old_subprojects' => env('DELETE_OLD_SUBPROJECTS', true),
    'github_app_id' => env('GITHUB_APP_ID', null),
    'geolocate_ip_addresses' => env('GEOLOCATE_IP_ADDRESSES', true),
    'large_text_limit' => env('LARGE_TEXT_LIMIT', 0),
    'login_field' => env('LOGIN_FIELD', 'Email'),
    'max_upload_quota' => env('MAX_UPLOAD_QUOTA', 10),
    'slow_page_time' => env('SLOW_PAGE_TIME', 10),
    'token_duration' => env('TOKEN_DURATION', 15811200),
    'unlimited_projects' => $unlimited_projects,
    'use_compression' => env('USE_COMPRESSION', true),

];

<?php

use App\Enums\SubmissionValidationType;
use Illuminate\Support\Str;

$cdash_directory_name = env('CDASH_DIRECTORY', 'cdash');
$cdash = realpath(app_path($cdash_directory_name));

// read in all of our cdash config files
if ($cdash) {
    set_include_path(base_path('/app/cdash'));
    include_once 'bootstrap/cdash_autoload.php';
}

$unlimited_projects = json_decode(env('UNLIMITED_PROJECTS', ''), true);
if (!is_array($unlimited_projects)) {
    $unlimited_projects = [];
}

return [
    'password' => [
        'complexity' => env('MINIMUM_PASSWORD_COMPLEXITY', 0),
        'count' => env('PASSWORD_COMPLEXITY_COUNT', 0),
        'min' => env('MINIMUM_PASSWORD_LENGTH', 5),
        'expires' => env('PASSWORD_EXPIRATION', 0),
    ],
    'version' => '4.2.0',
    'registration' => [
        'email' => [
            'verify' => env('REGISTRATION_EMAIL_VERIFY', true),
        ],
    ],
    'file' => [
        'path' => [
            'js' => [
                'controllers' => base_path('public/assets/js/angular/controllers'),
                'version' => base_path('public/assets/js/angular/version.js'),
            ],
            'public' => base_path('public'),
        ],
    ],
    'login' => [
        'max_attempts' => env('LOCKOUT_ATTEMPTS', 5),
        'lockout' => [
            'duration' => env('LOCKOUT_LENGTH', 1),
        ],
    ],
    'autoremove_builds' => env('AUTOREMOVE_BUILDS', true),
    'backup_timeframe' => env('BACKUP_TIMEFRAME', 48),
    'builds_per_project' => env('BUILDS_PER_PROJECT', 0),
    'coverage_dir' => env('CDASH_COVERAGE_DIR', '/cdash/_build/xdebugCoverage'),
    'daily_updates' => env('DAILY_UPDATES', true),
    'default_project' => env('DEFAULT_PROJECT', null),
    'delete_old_subprojects' => env('DELETE_OLD_SUBPROJECTS', true),
    'github_always_pass' => env('GITHUB_ALWAYS_PASS', false),
    'github_app_id' => env('GITHUB_APP_ID', null),
    'github_private_key' => env('GITHUB_PRIVATE_KEY', null),
    'github_webhook_secret' => env('GITHUB_WEBHOOK_SECRET', null),
    'geolocate_ip_addresses' => env('GEOLOCATE_IP_ADDRESSES', true),
    'large_text_limit' => env('LARGE_TEXT_LIMIT', 0),
    'login_field' => env('LOGIN_FIELD', 'Email'),
    'max_upload_quota' => env('MAX_UPLOAD_QUOTA', 10),
    'notify_pull_request' => env('NOTIFY_PULL_REQUEST', false),
    'queue_timeout' => env('QUEUE_TIMEOUT', 2000),
    'remote_workers' => env('REMOTE_WORKERS', false),
    'retry_base' => env('QUEUE_RETRY_BASE', 5),
    'show_last_submission' => env('SHOW_LAST_SUBMISSION', true),
    'slow_page_time' => env('SLOW_PAGE_TIME', 10),
    'token_duration' => env('TOKEN_DURATION', 15811200),
    'validate_submissions' => match (Str::upper((string) env('VALIDATE_SUBMISSIONS'))) {
        'SILENT' => SubmissionValidationType::SILENT,
        'WARN' => SubmissionValidationType::WARN,
        'REJECT' => SubmissionValidationType::REJECT,
        default => SubmissionValidationType::SILENT,
    },
    // Specify whether users are allowed to create "full access" authentication tokens
    'allow_full_access_tokens' => env('ALLOW_FULL_ACCESS_TOKENS', true),
    // Specify whether users are allowed to create "submit only" tokens which are valid for all projects
    'allow_submit_only_tokens' => env('ALLOW_SUBMIT_ONLY_TOKENS', true),
    'unlimited_projects' => $unlimited_projects,
    'user_create_projects' => env('USER_CREATE_PROJECTS', false),
    // Defaults to public.  Only meaningful if USER_CREATE_PROJECT=true.
    'max_project_visibility' => env('MAX_PROJECT_VISIBILITY', 'PUBLIC'),
    'require_authenticated_submissions' => env('REQUIRE_AUTHENTICATED_SUBMISSIONS', false),
    'use_vcs_api' => env('USE_VCS_API', true),
    'require_full_email_when_adding_user' => env('REQUIRE_FULL_EMAIL_WHEN_ADDING_USER', false),
    // Whether or not project administrators can invite users
    'project_admin_registration_form_enabled' => env('PROJECT_ADMIN_REGISTRATION_FORM_ENABLED', true),
    // Text displayed at the top of all pages.  Limited to 40 characters.
    'global_banner' => env('GLOBAL_BANNER'),
    // Whether or not "normal" username+password authentication is enabled
    'username_password_authentication_enabled' => env('USERNAME_PASSWORD_AUTHENTICATION_ENABLED', true),
    'ldap_enabled' => env('CDASH_AUTHENTICATION_PROVIDER') === 'ldap',
];

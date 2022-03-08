<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:migrate {output=.env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate old CDash config variables to Laravel environment';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $output_filename = $this->argument('output');
        if ($output_filename === '.env') {
            $output_filename = base_path('.env');
        }

        $cdash_directory_name = env('CDASH_DIRECTORY', 'cdash');
        $cdash_app_dir = realpath(app_path($cdash_directory_name));

        if (!$cdash_app_dir) {
            $this->error("CDash app dir ($cdash_app_dir) not found");
        }

        if (!is_writable($output_filename)) {
            $this->error("File ($output_filename) is not writable");
        }

        // Parse existing .env file.
        if (!$handle = fopen($output_filename, 'r')) {
            $this->error("Cannot open file ($output_filename) for reading");
        }
        $config = [];
        while (($line = fgets($handle)) !== false) {
            // Skip comments.
            if (strpos($line, '#') === 0) {
                continue;
            }
            // Only consider lines that have an equal sign in them.
            if (strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line);
            $config[$key] = $value;
        }
        fclose($handle);

        // Backup existing .env file before modifying it.
        if (file_exists($output_filename)) {
            copy($output_filename, "{$output_filename}.bak");
        }

        // Get default values of legacy configuration variables.
        $legacy_defaults = [];
        $ONLY_LOAD_DEFAULTS = true;
        include $cdash_app_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        include $cdash_app_dir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'version.php';
        foreach (get_defined_vars() as $key => $value) {
            if (strpos($key, 'CDASH_') === 0) {
                $legacy_defaults[$key] = $value;
            }
        }
        unset($ONLY_LOAD_DEFAULTS);

        // This is a mapping of legacy-to-Laravel configuration variable names.
        $legacy_to_laravel_names = [
            'MAININDEX_TITLE' => 'APP_NAME',
            'TESTING_MODE' => 'APP_DEBUG',
            'BASE_URL' => 'APP_URL',
            'DB_TYPE' => 'DB_CONNECTION',
            'DB_NAME' => 'DB_DATABASE',
            'DB_PASS' => 'DB_PASSWORD',
            'DB_LOGIN' => 'DB_USERNAME',
            'EMAIL_FROM' => 'MAIL_FROM_ADDRESS',
            'EMAIL_REPLY' => 'MAIL_REPLY_ADDRESS',
            'EMAIL_SMTP_HOST' => 'MAIL_HOST',
            'EMAIL_SMTP_PORT' => 'MAIL_PORT',
            'EMAIL_SMTP_LOGIN' => 'MAIL_USERNAME',
            'EMAIL_SMTP_PASS' => 'MAIL_PASSWORD',
            'EMAIL_SMTP_ENCRYPTION' => 'MAIL_ENCRYPTION',
            'LDAP_HOSTNAME' => 'LDAP_HOSTS',
            'LDAP_BASEDN' => 'LDAP_BASE_DN',
            'LDAP_BIND_DN' => 'LDAP_USERNAME',
            'LDAP_BIND_PASSWORD' => 'LDAP_PASSWORD',
            'LDAP_FILTER' => 'LDAP_FILTERS_ON',
        ];

        // Read CDash config files, parsing their variables and changing
        // the names to Laravel equivalents as necessary.
        include $cdash_app_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        include $cdash_app_dir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'version.php';
        foreach (get_defined_vars() as $key => $value) {
            if ($key == 'OAUTH2_PROVIDERS') {
                foreach ($value as $k => $v) {
                    $provider = strtoupper($k);
                    if (array_key_exists('clientId', $v)) {
                        $config["{$provider}_CLIENT_ID"] = $v['clientId'];
                    }
                    if (array_key_exists('clientSecret', $v)) {
                        $config["{$provider}_CLIENT_SECRET"] = $v['clientSecret'];
                    }
                    if (array_key_exists('domain', $v)) {
                        $config["{$provider}_DOMAIN"] = $v['domain'];
                    }
                    if (array_key_exists('clientId', $v) && array_key_exists('clientSecret', $v)) {
                        $config["{$provider}_ENABLE"] = true;
                    }
                }
            }

            if (strpos($key, 'CDASH_') !== 0) {
                continue;
            }

            if (is_array($value)) {
                if (empty($value)) {
                    continue;
                }
                $value = '["' . implode('","', $value) . '"]';
            }
            if (array_key_exists($key, $legacy_defaults) &&
                    $value === $legacy_defaults[$key]) {
                continue;
            }
            $key = substr($key, strlen('CDASH_'));
            if (array_key_exists($key, $legacy_to_laravel_names)) {
                $key = $legacy_to_laravel_names[$key];
            }

            // Special handling of config variables that legacy CDash and
            // Laravel treat differently.
            if ($key === 'PRODUCTION_MODE') {
                $key = 'APP_ENV';
                if ($value) {
                    $value = 'production';
                } else {
                    $value = 'development';
                }
            } elseif ($key === 'COOKIE_EXPIRATION_TIME') {
                $key = 'SESSION_LIFETIME';
                // Convert from seconds to minutes.
                $value /= 60;
            } elseif ($key === 'LOG_LEVEL') {
                require_once 'include/log.php';
                $key = 'APP_LOG_LEVEL';
                $value = to_psr3_level($value);
            }

            /* still TODO for special handling:
             * associative arrays that will need code-level changes:
                 MEMCACHE_SERVER, GOOGLE_MAP_API_KEY
             */
            // End special handling

            // If a value is set in both places, .env gets priority over
            // config.local.php.
            if (array_key_exists($key, $config)) {
                continue;
            }

            // Serving over https means 'production mode' to Laravel.
            if ($key === 'APP_URL' && substr($value, 0, 5) === 'https') {
                $config['APP_ENV'] = 'production';
            }

            $config[$key] = rtrim($value);
        }
        // Also record timezone is a non-default value was set in config.local.php.
        $timezone = date_default_timezone_get();
        if ($timezone != 'UTC' && !array_key_exists('APP_TIMEZONE', $config)) {
            $config['APP_TIMEZONE'] = $timezone;
        }

        // Write out our newly updated .env file.
        ksort($config);
        if (!$handle = fopen($output_filename, 'w')) {
            $this->error("Cannot open file ($output_filename) for writing");
        }
        foreach ($config as $key => $value) {
            fwrite($handle, "$key=$value\n");
        }
        fclose($handle);
    }
}

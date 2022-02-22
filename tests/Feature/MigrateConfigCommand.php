<?php

namespace Tests\Feature;

use Tests\TestCase;

class MigrateConfigCommand extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();
        $this->config_file = base_path('app/cdash/config/config.local.php');
        $this->test_file = base_path('tests/.env.testing');
    }

    /**
     * Feature test for the config:migrate artisan command.
     *
     * @return void
     */
    public function testMigrateConfigCommand()
    {
        // Backup existing config.local file.
        rename($this->config_file, "{$this->config_file}.bak");

        // Write an example config.local.php file.
        $config_contents = <<<'EOT'
<?php
date_default_timezone_set('America/New_York');
$CDASH_TESTING_MODE = true;
$CDASH_DB_TYPE = 'mysql';
$CDASH_DB_NAME = 'cdash4simpletest';
$CDASH_DB_PASS = 'my_fake_cdash_db_password';
$CDASH_DB_LOGIN = 'my_fake_cdash_db_user';
$CDASH_COOKIE_EXPIRATION_TIME = '7200';
$CDASH_EMAIL_SMTP_HOST = 'cdash_smtp_host';
$CDASH_EMAIL_SMTP_LOGIN = 'cdash_smtp_user';
$CDASH_EMAIL_SMTP_PASS = 'cdash_smtp_password';
$CDASH_BASE_URL = 'https://localhost/CDash';
$CDASH_LOG_LEVEL = LOG_DEBUG;
$CDASH_UNLIMITED_PROJECTS = ['Project1', 'Project2'];
$OAUTH2_PROVIDERS['GitHub'] = [
    'clientId'          => 'github_client_id',
    'clientSecret'      => 'github_client_secret'
];
$OAUTH2_PROVIDERS['GitLab'] = [
    'clientId'          => 'gitlab_client_id',
    'clientSecret'      => 'gitlab_client_secret',
    'domain'            => 'https://gitlab.kitware.com'
];
$OAUTH2_PROVIDERS['Google'] = [
    'clientId'          => 'google_client_id',
    'clientSecret'      => 'google_client_secret'
];
EOT;
        file_put_contents($this->config_file, $config_contents);

        // Make a copy of the example .env file and add a database password.
        copy(base_path('.env.example'), $this->test_file);
        file_put_contents($this->test_file,
                "\nDB_PASSWORD=my_db_password\n",
                FILE_APPEND);

        // Run the migration command.
        $this->artisan('config:migrate', ['output' => $this->test_file]);

        // Verify expected contents in .env.
        $actual = file_get_contents($this->test_file);
        $this->assertContains('APP_DEBUG=1', $actual);
        $this->assertContains('APP_URL=https://localhost/CDash', $actual);
        $this->assertContains('APP_ENV=production', $actual);
        $expected = <<<'EOT'
MIX_APP_URL="${APP_URL}"
EOT;
        $this->assertContains($expected, $actual);
        $this->assertContains('SESSION_LIFETIME=120', $actual);
        $this->assertContains('MAIL_HOST=cdash_smtp_host', $actual);
        $this->assertContains('MAIL_PASSWORD=cdash_smtp_password', $actual);
        $this->assertContains('MAIL_USERNAME=cdash_smtp_user', $actual);
        $this->assertContains('APP_TIMEZONE=America/New_York', $actual);
        $this->assertContains('APP_LOG_LEVEL=debug', $actual);
        $this->assertContains('UNLIMITED_PROJECTS=["Project1","Project2"]', $actual);
        $this->assertContains('GITHUB_CLIENT_ID=github_client_id', $actual);
        $this->assertContains('GITHUB_ENABLE=', $actual);
        $this->assertContains('GITHUB_CLIENT_SECRET=github_client_secret', $actual);
        $this->assertContains('GITLAB_CLIENT_ID=gitlab_client_id', $actual);
        $this->assertContains('GITLAB_ENABLE=', $actual);
        $this->assertContains('GITLAB_CLIENT_SECRET=gitlab_client_secret', $actual);
        $this->assertContains('GITLAB_DOMAIN=https://gitlab.kitware.com', $actual);
        $this->assertContains('GOOGLE_CLIENT_ID=google_client_id', $actual);
        $this->assertContains('GOOGLE_ENABLE=', $actual);
        $this->assertContains('GOOGLE_CLIENT_SECRET=google_client_secret', $actual);

        // Default value (mysql) does not get written to .env.
        $this->assertNotContains('DB_CONNECTION=', $actual);

        // Existing values in .env will not get overwritten.
        $this->assertContains('DB_PASSWORD=my_db_password', $actual);
        $this->assertNotContains('DB_PASSWORD=my_fake_testing_db_password', $actual);
    }

    public function tearDown() : void
    {
        // Remove testing files.
        if (file_exists($this->test_file)) {
            unlink($this->test_file);
        }
        if (file_exists("{$this->test_file}.bak")) {
            unlink("{$this->test_file}.bak");
        }

        // Restore original config.local file.
        rename("{$this->config_file}.bak", $this->config_file);
    }
}

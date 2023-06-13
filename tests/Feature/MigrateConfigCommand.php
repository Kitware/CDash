<?php

namespace Tests\Feature;

use Tests\TestCase;

class MigrateConfigCommand extends TestCase
{
    protected $config_file;
    protected $test_file;

    public function setUp() : void
    {
        parent::setUp();
        $this->config_file = base_path('app/cdash/config/config.local.php');
        $this->test_file = base_path('tests/.env.testing');
        if (!file_exists($this->config_file)) {
            file_put_contents($this->config_file, "");
        }
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
$CDASH_GOOGLE_MAP_API_KEY['cdash.org'] = 'ABC123';
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

        file_put_contents($this->test_file, <<<'EOT'
MIX_APP_URL="${APP_URL}"
DB_PASSWORD=my_db_password
EOT, FILE_APPEND);

        // Run the migration command.
        $this->artisan('config:migrate', ['output' => $this->test_file]);

        // Verify expected contents in .env.
        $actual = file_get_contents($this->test_file);
        $this::assertStringContainsString('APP_DEBUG=1', $actual);
        $this::assertStringContainsString('APP_URL=https://localhost/CDash', $actual);
        $this::assertStringContainsString('APP_ENV=production', $actual);
        $expected = <<<'EOT'
MIX_APP_URL="${APP_URL}"
EOT;
        $this::assertStringContainsString($expected, $actual);
        $this::assertStringContainsString('SESSION_LIFETIME=120', $actual);
        $this::assertStringContainsString('MAIL_HOST=cdash_smtp_host', $actual);
        $this::assertStringContainsString('MAIL_PASSWORD=cdash_smtp_password', $actual);
        $this::assertStringContainsString('MAIL_USERNAME=cdash_smtp_user', $actual);
        $this::assertStringContainsString('APP_TIMEZONE=America/New_York', $actual);
        $this::assertStringContainsString('APP_LOG_LEVEL=debug', $actual);
        $this::assertStringContainsString('UNLIMITED_PROJECTS=["Project1","Project2"]', $actual);
        $this::assertStringContainsString('GOOGLE_MAP_API_KEY=ABC123', $actual);
        $this::assertStringContainsString('GITHUB_CLIENT_ID=github_client_id', $actual);
        $this::assertStringContainsString('GITHUB_ENABLE=', $actual);
        $this::assertStringContainsString('GITHUB_CLIENT_SECRET=github_client_secret', $actual);
        $this::assertStringContainsString('GITLAB_CLIENT_ID=gitlab_client_id', $actual);
        $this::assertStringContainsString('GITLAB_ENABLE=', $actual);
        $this::assertStringContainsString('GITLAB_CLIENT_SECRET=gitlab_client_secret', $actual);
        $this::assertStringContainsString('GITLAB_DOMAIN=https://gitlab.kitware.com', $actual);
        $this::assertStringContainsString('GOOGLE_CLIENT_ID=google_client_id', $actual);
        $this::assertStringContainsString('GOOGLE_ENABLE=', $actual);
        $this::assertStringContainsString('GOOGLE_CLIENT_SECRET=google_client_secret', $actual);

        // Existing values in .env will not get overwritten.
        $this::assertStringContainsString('DB_PASSWORD=my_db_password', $actual);
        $this::assertStringNotContainsString('DB_PASSWORD=my_fake_testing_db_password', $actual);
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

        parent::tearDown();
    }
}

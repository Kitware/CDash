<?php

namespace Tests\Feature;

use Tests\TestCase;

class AutoRemoveBuildsCommand extends TestCase
{
    public function setUp()
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
$CDASH_EMAIL_SMTP_HOST = 'cdash_smtp_host';
$CDASH_EMAIL_SMTP_LOGIN = 'cdash_smtp_user';
$CDASH_EMAIL_SMTP_PASS = 'cdash_smtp_password';
$CDASH_BASE_URL = 'http://localhost/CDash';
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
        $this->assertContains('MAIL_HOST=cdash_smtp_host', $actual);
        $this->assertContains('MAIL_PASSWORD=cdash_smtp_password', $actual);
        $this->assertContains('MAIL_USERNAME=cdash_smtp_user', $actual);
        $this->assertContains('BASE_URL=http://localhost/CDash', $actual);
        $this->assertContains('APP_TIMEZONE=America/New_York', $actual);

        // Default value (mysql) does not get written to .env.
        $this->assertNotContains('DB_CONNECTION=', $actual);

        // Existing values in .env will not get overwritten.
        $this->assertContains('DB_PASSWORD=my_db_password', $actual);
        $this->assertNotContains('DB_PASSWORD=my_fake_testing_db_password', $actual);
    }

    public function tearDown()
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

<?php

use Illuminate\Support\Facades\Artisan;

require_once __DIR__ . '/cdash_test_case.php';

class InstallTestCase extends KWWebTestCase
{
    protected string $databaseName;

    public function __construct()
    {
        parent::__construct();

        $db_type = config('database.default');
        $db_config = config("database.connections.{$db_type}");
        $this->databaseName = $db_config['database'];
    }

    public function testInstall()
    {
        // double check that it's the testing database before doing anything hasty...
        if ($this->databaseName !== 'cdash4simpletest') {
            $this->fail("can only test on a database named 'cdash4simpletest'");
            return 1;
        }

        // drop any old testing database before testing install
        $success = $this->db->drop($this->databaseName);
        if (!$success) {
            $this->fail('Error dropping database');
            return 1;
        }

        $this->db->create($this->databaseName);

        Artisan::call('migrate', ['--force' => true]);

        Artisan::call('user:save', [
            '--email' => 'simpletest@localhost',
            '--firstname' => 'administrator',
            '--lastname' => '',
            '--password' => 'simpletest',
            '--institution' => 'Kitware Inc.',
            '--admin' => 1,
        ]);

        $this->pass('Passed');
    }
}

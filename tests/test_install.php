<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class InstallTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();

        global $db;
        $this->databaseName = $db['name'];
    }

    public function testInstall()
    {
        //double check that it's the testing database before doing anything hasty...
        if ($this->databaseName !== 'cdash4simpletest') {
            $this->fail("can only test on a database named 'cdash4simpletest'");
            return 1;
        }

        //drop any old testing database before testing install
        $success = $this->db->drop($this->databaseName);
        if (!$success) {
            $this->fail('Error dropping database');
            return 1;
        }

        // Create the database
        if ($this->db->type == 'pgsql') {
            if (!$this->db->create($this->databaseName)) {
                $dbcreated = false;
                $msg = 'error query(CREATE DATABASE)';
                die('Error' . ' File: ' . __FILE__ . ' on line: ' . __LINE__ . ": $msg");
                return false;
            }
        }

        $this->setConnectionTimeout(99999);
        $this->get($this->url . '/install.php');
        if (!$this->setFieldByName('admin_email', 'simpletest@localhost')) {
            $this->fail('Set admin email returned false');
            return 1;
        }
        if (!$this->setFieldByName('admin_password', 'simpletest')) {
            $this->fail('Set admin password returned false');
            return 1;
        }
        $this->clickSubmitByName('Submit');
        $response = $this->getBrowser()->getContentAsText();
        if (strpos($response, 'successfully created') === false) {
            $this->fail("Unable to create database.");
            return 1;
        }
        $this->pass('Passed');
    }
}

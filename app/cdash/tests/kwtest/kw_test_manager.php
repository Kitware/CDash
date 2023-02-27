<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

use Illuminate\Support\Facades\Artisan;

require_once dirname(dirname(__FILE__)) . '/config.test.php';
require_once dirname(__FILE__) . '/simpletest/unit_tester.php';
require_once dirname(__FILE__) . '/simpletest/mock_objects.php';
require_once dirname(__FILE__) . '/simpletest/web_tester.php';
require_once dirname(__FILE__) . '/kw_db.php';
require_once dirname(__FILE__) . '/kw_unlink.php';

/**
 * The test manager interface kw tests with simpletest test.
 */
class TestManager
{
    public $testDir = null;
    public $database = null;

    public function runFileTest(&$reporter, $file)
    {
        $test = new TestSuite('All Tests');
        if ($this->testDir !== null) {
            $path = $this->testDir . '/' . $file;
        } else {
            $path = $file;
        }
        echo "$path\n";
        $test->addFile($path);
        return $test->run($reporter);
    }

    /**
     * run all the tests
     * @return bool the result the test running
     * @param object $reporter
     */
    public function runAllTests(&$reporter)
    {
        $testsFile = $this->getTestCaseList();
        $test = new TestSuite('All Tests');
        foreach ($testsFile as $path => $file) {
            $test->addFile($path);
        }
        return $test->run($reporter);
    }

    /**
     * Match all the test files inside the test directory
     * @return array an array of the test files
     */
    public function getTestCaseList()
    {
        if (!$this->testDir) {
            die("please, set the test directory\n");
        }
        $testsFile = array();
        foreach (glob($this->testDir . '/test_*.php') as $file) {
            $fileinfo = pathinfo($file);
            if (strcmp($fileinfo['basename'], 'test_install.php') != 0 &&
                strcmp($fileinfo['basename'], 'test_uninstall.php') != 0
            ) {
                $testsFile[$fileinfo['dirname'] . '/' . $fileinfo['basename']] = $fileinfo['basename'];
            }
        }
        return $testsFile;
    }

    /**
     * drop the old test database
     * @return bool success/failure depending of the database dropping
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $dbname
     * @param string $dbtype
     */
    public function _uninstalldb4test($host, $port, $user, $password, $dbname, $dbtype)
    {
        if (!strcmp($dbname, 'cdash4simpletest')) {
            $database = new database($dbtype);
            $database->setHost($host);
            $database->setPort($port);
            $database->setUser($user);
            $database->setPassword($password);
            return $database->drop($dbname);
        } else {
            die("We cannot test cdash because test database is not cdash4simpletest\n");
        }
    }

    /**
     * create the new test database
     * @return bool success/failure depending of the database creating
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $dbname
     * @param string $dbtype
     */
    public function _installdb4test($host, $port, $user, $password, $dbname, $dbtype)
    {
        if (!strcmp($dbname, 'cdash4simpletest')) {
            $database = new database($dbtype);
            $database->setHost($host);
            $database->setPort($port);
            $database->setUser($user);
            $database->setPassword($password);
            $dbcreated = true;
            if (!$database->create($dbname)) {
                $dbcreated = false;
                $msg = 'error query(CREATE DATABASE)';
                die('Error' . ' File: ' . __FILE__ . ' on line: ' . __LINE__ . ": $msg");
                return false;
            }
            if ($dbcreated) {
                Artisan::call('migrate');
            }
            return true;
        } else {
            die("We cannot test cdash because test database is not cdash4simpletest\n");
        }
    }
}

class HtmlTestManager extends TestManager
{
    public function runAllTests(&$reporter)
    {
        $this->_uninstalldb4test($this->database['host'],
            $this->database['port'],
            $this->database['login'],
            $this->database['pwd'],
            $this->database['name'],
            $this->database['type']);
        $this->_installdb4test($this->database['host'],
            $this->database['port'],
            $this->database['login'],
            $this->database['pwd'],
            $this->database['name'],
            $this->database['type']);
        return parent::runAllTests($reporter);
    }
}

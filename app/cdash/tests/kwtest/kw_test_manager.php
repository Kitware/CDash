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

    public function setDatabase($db)
    {
        $this->database = $db;
    }

    /**
     * set the tests directory where the test files are placed
     * @param string $dir
     */
    public function setTestDirectory($dir)
    {
        $this->testDir = $dir;
    }

    /** Delete the log file */
    public function removeLogAndBackupFiles($logfilename)
    {
        $config = \CDash\Config::getInstance();
        if (file_exists($logfilename)) {
            if ($config->get('CDASH_TESTING_RENAME_LOGS')) {
                // Rename to a random name to keep for later inspection:
                //
                rename($logfilename, $config->get('CDASH_LOG_DIRECTORY') . '/cdash.' . microtime(true) . '.' . bin2hex(random_bytes(2)) . '.log');
            } else {
                // Delete file:
                cdash_testsuite_unlink($logfilename);
            }
        }

        $filenames = glob("{$config->get('CDASH_BACKUP_DIRECTORY')}/*");
        foreach ($filenames as $filename) {
            if (is_file($filename)) {
                cdash_testsuite_unlink($filename);
            }
        }
    }
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
     * @return the result the test running
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
     * @return an array of the test files
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
     * perform a connection to the database
     * @return the result of the connection
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $dbname
     * @param string $dbtype
     */
    public function _connectToDb($host, $port, $user, $password, $dbname, $dbtype)
    {
        $database = new database($dbtype);
        $database->setHost($host);
        $database->setPort($port);
        $database->setUser($user);
        $database->setPassword($password);
        $database->setDb($dbname);
        return $database->connectedToDb();
    }

    /**
     * drop the old test database
     * @return success/failure depending of the database dropping
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
     * @return success/failure depending of the database creating
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
                $dirname = str_replace('\\', '/', dirname(__FILE__));
                $sqlfile = str_replace('/tests/kwtest', '', $dirname) . '/sql/' . $dbtype . '/cdash.sql';
                $database->fillDb($sqlfile);
            }
            return true;
        } else {
            die("We cannot test cdash because test database is not cdash4simpletest\n");
        }
    }
}

/**
 * The cdash test manager interface cdash test with simpletest
 */
class CDashTestManager extends TestManager
{
    public $_urlToCdash = null;

    /**
     * run all the tests in the current directory
     * @return the result of the test
     * @param object $reporter
     */
    public function runAllTests(&$reporter)
    {
        $reporter->paintTestCaseList($this->getTestCaseList());
        parent::runAllTests($reporter);
    }

    /**
     *    Set the url of the CDash server
     * @param string $url url via we make the curl to send the report
     */
    public function setCDashServer($servername)
    {
        if (!empty($servername)) {
            $this->_urlToCdash = $servername;
        }
    }

    /**
     * update the svn repository
     * @param object $reporter
     * @param string $svnroot
     */

    public function updateSVN($reporter, $svnroot, $type)
    {
        if (!empty($svnroot)) {
            $reporter->paintUpdateStart();
            $execution_time = $this->__performSvnUpdate($reporter, $svnroot, $type);
            // The project is up to date and the type is Continuous
            if (!$execution_time) {
                echo "error: updateSVN: false execution_time, no paintUpdateEnd\n";
                return false;
            }
            // We put in minute the execution time of the svn update
            if (is_numeric($execution_time)) {
                $execution_time = round($execution_time / 60, 3);
            }
            $reporter->paintUpdateEnd($execution_time);
            return true;
        }

        echo "error: updateSVN: empty svnroot\n";
        return false;
    }

    /**
     *    perform an update of a revision in the svn
     * @return the time execution of the svn update
     * @param object $reporter
     * @param string $svnroot
     */
    public function __performSvnUpdate($reporter, $svnroot, $type)
    {
        $time_start = (float)array_sum(explode(' ', microtime()));
        $grepCmd = 'grep';
        global $isWindows;
        if ($isWindows) {
            $grepCmd = 'findstr';
        }
        $raw_output = $this->__performSvnCommand(`svn info "$svnroot" 2>&1 | $grepCmd Revision`);
        // We catch the current revision of the repository
        $currentRevision = str_replace('Revision: ', '', $raw_output[0]);
        $raw_output = $this->__performSvnCommand(`svn update "$svnroot" 2>&1 | $grepCmd revision`);
        if (strpos($raw_output[0], 'revision') === false) {
            $execution_time = "Svn Error:\nsvn update did not return the right standard output.\n";
            $execution_time .= "svn update did not work on your repository\n";
            echo "error: __performSvnUpdate: svn update failed\n";
            return $execution_time;
        }
        if (strpos($raw_output[0], 'At revision') !== false) {
            if (!strcmp($type, 'Continuous')) {
                echo "__performSvnUpdate: type=[$type], returning false\n";
                return false;
            }
            $time_end = (float)array_sum(explode(' ', microtime()));
            $execution_time = $time_end - $time_start;
            echo "Old revision of repository is: $currentRevision\nCurrent revision of repository is: $currentRevision\n";
            echo "Project is up to date\n";
            return $execution_time;
        }
        $newRevision = str_replace('Updated to revision ', '', $raw_output[0]);
        $newRevision = strtok($newRevision, '.');
        $raw_output = `svn log "$svnroot" -r $currentRevision:$newRevision -v --xml 2>&1`;
        $reporter->paintUpdateFile($raw_output);
        $time_end = (float)array_sum(explode(' ', microtime()));
        $execution_time = $time_end - $time_start;
        echo "Your Repository has just been updated from revision $currentRevision to revision $newRevision\n";
        echo "\tRepository concerned: [$svnroot]\n";
        echo "\tUse SVN repository type\n";
        echo "Project is up to date\n";
        return $execution_time;
    }

    /**
     * perform a command line
     * @return an array of the output result of the commandline
     * @param command $commandline
     */
    public function __performSvnCommand($commandline)
    {
        return explode("\n", $commandline);
    }

    /**
     * configure the database for the test by droping the old
     * test database and creating a new one
     * @param object $reporter
     * @param array $db
     */
    public function configure($reporter, $logfilename)
    {
        if (!$this->database) {
            echo "Please, set the database to the test manager before configure the test\n";
            return false;
        }
        $reporter->paintConfigureStart();
        $time_start = (float)array_sum(explode(' ', microtime()));
        $result = $this->_uninstalldb4test($this->database['host'],
            $this->database['port'],
            $this->database['login'],
            $this->database['pwd'],
            $this->database['name'],
            $this->database['type']);
        $time_end = (float)array_sum(explode(' ', microtime()));
        $execution_time = $time_end - $time_start;
        $time_start = $time_end;
        $reporter->paintConfigureUninstallResult($result);
        $result = $this->_connectToDb($this->database['host'],
            $this->database['port'],
            $this->database['login'],
            $this->database['pwd'],
            $this->database['name'],
            $this->database['type']);
        $reporter->paintConfigureConnection($result);

        if (file_exists($logfilename)) {
            // delete the log file -- result is success/failure
            $result = cdash_testsuite_unlink($logfilename);
        } else {
            // file is already not there -- equivalent to successfully deleting it
            $result = true;
        }
        $reporter->paintConfigureDeleteLogResult($result, $logfilename);

        $result = $this->_installdb4test($this->database['host'],
            $this->database['port'],
            $this->database['login'],
            $this->database['pwd'],
            $this->database['name'],
            $this->database['type']);
        $time_end = (float)array_sum(explode(' ', microtime()));
        $execution_time += ($time_end - $time_start);
        $execution_time = round($execution_time / 60, 3);
        $reporter->paintConfigureInstallResult($result);
        $result = $this->_connectToDb($this->database['host'],
            $this->database['port'],
            $this->database['login'],
            $this->database['pwd'],
            $this->database['name'],
            $this->database['type']);
        $reporter->paintConfigureConnection($result);
        $reporter->paintConfigureEnd($execution_time);
        return $result;
    }

    /**
     * Check the log file of the application testing
     * @return false if there is no log file or no error into the log file
     *         true if it caught some errors from the log file
     * @param object $application
     * @param object $reporter
     */
    public function getErrorFromServer($filename, $reporter)
    {
        if (!file_exists($filename)) {
            return false;
        }
        $content = file_get_contents($filename);
        if (empty($content)) {
            return false;
        }
        // For midas cake: the log time looks like this: if it is not
        // a cake midas application that you're testing, comment the following line
        // and implement your own regex
        //$regex = "#[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}#";

        // the regex to catch the date for cdash: model: [2009-02-25T18:24:56]
        $regex = "#[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\]#";
        $fp = fopen($filename, 'r');
        $content = fread($fp, filesize($filename));
        fclose($fp);
        unset($fp);
        $output = preg_split($regex, $content);
        foreach ($output as $message) {
            $reporter->paintServerFail($message);
        }
        return true;
    }

    /**
     *    Send via a curl to the CDash server the xml reports
     * @return true on success / false on failure
     */
    public function sendToCdash($reporter, $directory)
    {
        if (!$this->_urlToCdash) {
            echo "please set the url to the cdash server before calling sendToCdash method\n";
            return false;
        }
        $reporter->close();
        $msg = "Submit files (using http)\n\tUsing HTTP submit method\n\t";
        $msg .= 'Drop site: ' . $this->_urlToCdash . "?project=CDash\n";
        echo $msg;
        $filename = $directory . '/Build.xml';
        $this->__uploadViaCurl($filename);
        echo "\tUploaded: $filename\n";
        $filename = $directory . '/Configure.xml';
        $this->__uploadViaCurl($filename);
        echo "\tUploaded: $filename\n";
        $filename = $directory . '/Test.xml';
        $this->__uploadViaCurl($filename);
        echo "\tUploaded: $filename\n";
        $filename = $directory . '/Update.xml';
        $this->__uploadViaCurl($filename);
        echo "\tUploaded: $filename\n";
        echo "\tSubmission successful\n";
        return true;
    }

    /**
     *    Perform a curl to upload the filename to the CDash Server
     * @param object $filename
     */
    public function __uploadViaCurl($filename)
    {
        $fp = fopen($filename, 'r');
        $ch = curl_init($this->_urlToCdash . '/submit.php?project=CDash');
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        unset($fp);
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
        parent::runAllTests($reporter);
    }
}

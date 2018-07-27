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

require_once dirname(__FILE__) . '/kw_unlink.php';

use CDash\Model\Project;

/**#@+
 *  include other SimpleTest class files
 */
require_once 'tests/kwtest/simpletest/web_tester.php';

/**
 *    Test case for testing of web pages. Allows
 *    fetching of pages, parsing of HTML and
 *    submitting forms.
 */
class KWWebTestCase extends WebTestCase
{
    public $url = null;
    public $db = null;
    public $logfilename = null;
    public $configfilename = null;
    public $cdashpro = null;

    public function __construct()
    {
        parent::__construct();

        global $configure;
        $this->url = $configure['urlwebsite'];
        $this->cdashpro = false;
        if (isset($configure['cdashpro']) && $configure['cdashpro'] == '1') {
            $this->cdashpro = true;
        }

        global $db;
        $this->db = new database($db['type']);
        $this->db->setDb($db['name']);
        $this->db->setHost($db['host']);
        $this->db->setPort($db['port']);
        $this->db->setUser($db['login']);
        $this->db->setPassword($db['pwd']);
        $this->db->setConnection($db['connection']);

        global $CDASH_LOG_FILE, $cdashpath;
        $this->logfilename = $CDASH_LOG_FILE;
        $this->configfilename = $cdashpath . '/config/config.local.php';
    }

    public function setUp()
    {
        $this->startCodeCoverage();
    }

    public function tearDown()
    {
        $this->stopCodeCoverage();
    }

    public function startCodeCoverage()
    {
        //echo "startCodeCoverage called...\n";
        if (extension_loaded('xdebug')) {
            xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
            //echo "xdebug_start_code_coverage called...\n";
        }
    }

    public function stopCodeCoverage()
    {
        //echo "stopCodeCoverage called...\n";
        if (extension_loaded('xdebug')) {
            $data = xdebug_get_code_coverage();
            xdebug_stop_code_coverage();
            //echo "xdebug_stop_code_coverage called...\n";
            global $CDASH_COVERAGE_DIR;
            $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
                md5($_SERVER['SCRIPT_FILENAME']);
            file_put_contents(
                $file . '.' . md5(uniqid(rand(), true)) . '.' . get_class(),
                serialize($data)
            );
        }
    }

    /**
     * find a string into another one
     * @return true if the search string has found or false in the other case
     * @param string $mystring
     * @param string $findme
     */
    public function findString($mystring, $findme)
    {
        if (strpos($mystring, $findme) === false) {
            return false;
        }
        return true;
    }

    /**
     * Try to connect to the website
     * @return the content if the connection succeeded
     *         or false if there were some errors
     * @param string $url
     */
    public function connect($url)
    {
        $page = $this->get($url);
        return $this->analyse($page);
    }

    /** Delete the log file */
    public function deleteLog($filename)
    {
        if (file_exists($filename)) {
            global $CDASH_TESTING_RENAME_LOGS;
            if ($CDASH_TESTING_RENAME_LOGS) {
                // Rename to a random name to keep for later inspection:
                //
                global $CDASH_LOG_DIRECTORY;
                rename($filename, $CDASH_LOG_DIRECTORY . '/cdash.' . microtime(true) . '.' . bin2hex(random_bytes(2)) . '.log');
            } else {
                // Delete file:
                cdash_testsuite_unlink($filename);
            }
        }
    }

    /** Look at the log file and return false if errors are found */
    public function checkLog($filename)
    {
        if (file_exists($filename)) {
            $content = file_get_contents($filename);
            if ($this->findString($content, 'ERROR') ||
                $this->findString($content, 'WARNING')
            ) {
                $this->fail('Log file has errors or warnings');
                return false;
            }
            return $content;
        }
        return true;
    }

    /** Compare the current log with a file */
    public function compareLog($logfilename, $template)
    {
        $log = '';
        if (file_exists($logfilename)) {
            $log = file_get_contents($logfilename);
            $log = str_replace("\r", '', $log);
        } else {
            $this->fail("Log file ${logfilename} does not exist: cannot continue");
            return false;
        }

        $templateLog = file_get_contents($template);
        $templateLog = str_replace("\r", '', $templateLog);

        // Compare char by char
        $il = 0;
        $it = 0;
        while ($il < strlen($log) && $it < strlen($templateLog)) {
            if ($templateLog[$it] == '<') {
                $pos2 = strpos($templateLog, '<NA>', $it);
                $pos3 = strpos($templateLog, "<NA>\n", $it);

                // We skip the line
                if ($pos3 == $it) {
                    while (($it < strlen($templateLog)) && ($templateLog[$it] != "\n")) {
                        $it++;
                    }
                    while (($il < strlen($log)) && ($log[$il] != "\n")) {
                        $il++;
                    }
                    continue;
                } // if we have the tag we skip the word
                elseif ($pos2 == $it) {
                    while (($it < strlen($templateLog)) && ($templateLog[$it] != ' ') && ($templateLog[$it] != '/') && ($templateLog[$it] != ']') && ($templateLog[$it] != '}') && ($templateLog[$it] != '"') && ($templateLog[$it] != '&')) {
                        $it++;
                    }
                    while (($il < strlen($log)) && ($log[$il] != ' ') && ($log[$il] != '/') && ($log[$il] != ']') && ($log[$il] != '}') && ($log[$il] != '"') && ($log[$il] != '&')) {
                        $il++;
                    }
                    continue;
                }
            }

            if ($log[$il] != $templateLog[$it]) {
                $this->fail("Log files are different\n  logfilename='$logfilename'\n  template='$template'\n  at char $it: " . ord($templateLog[$it]) . '=' . ord($log[$il]) . "\n  **" . substr($templateLog, $it, 10) . '** vs. **' . substr($log, $il, 10) . '**');
                return false;
            }
            $it++;
            $il++;
        }
        return true;
    }

    /** Check the current content for errors */
    public function checkErrors()
    {
        $content = $this->getBrowser()->getContent();
        if ($this->findString($content, 'error:')) {
            $this->assertNoText('error');
            return false;
        }
        if ($this->findString($content, 'Warning')) {
            $this->assertNoText('Warning');
            return false;
        }
        if ($this->findString($content, 'Notice')) {
            $this->assertNoText('Notice');
            return false;
        }
        return true;
    }

    /**
     * Analyse a website page
     * @return the content of the page if there is no errors
     *         otherwise false
     * @param object $page
     */
    public function analyse($page)
    {
        if (!$page) {
            $this->assertTrue(false, 'The requested URL was not found on this server');
            return false;
        }
        $browser = $this->getBrowser();
        $content = '';
        if ($browser->getResponseCode() == 200) {
            $content = $browser->getContent();
            if ($this->findString($content, ' error</b>:')) {
                $this->assertNoText('error');
                $error = true;
            }
            if ($this->findString($content, 'Warning:')) {
                $this->assertNoText('Warning');
                $error = true;
            }
            if ($this->findString($content, 'Notice:')) {
                $this->assertNoText('Notice');
                $error = true;
            }
        } else {
            $this->assertResponse(200, "The following url $page is not reachable");
            $error = true;
        }
        if (isset($error)) {
            return false;
        }
        return $content;
    }

    public function login($user = 'simpletest@localhost', $passwd = 'simpletest')
    {
        $this->get($this->url . '/login.php');
        $this->setField('login', $user);
        $this->setField('passwd', $passwd);
        return $this->clickSubmitByName('sent');
    }

    public function logout()
    {
        $this->get($this->url);
        return $this->clickLink('Log Out');
    }

    public function userExists($email)
    {
        require_once('include/common.php');
        require_once('include/pdo.php');
        $pdo = get_link_identifier()->getPdo();
        $user_table = qid('user');
        $stmt = $pdo->prepare("SELECT id FROM $user_table WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            return false;
        }
        return true;
    }

    public function submission($projectname, $file, $header = null)
    {
        global $CDASH_BERNARD_SUBMISSION;

        $url = $this->url . "/submit.php?project=$projectname";
        $result = $this->uploadfile($url, $file, $header);
        if ($result === false) {
            return false;
        }

        if ($CDASH_BERNARD_SUBMISSION) {
            sleep(1);
        }

        if ($this->findString($result, 'error') ||
            $this->findString($result, 'Warning') ||
            $this->findString($result, 'Notice')
        ) {
            $this->assertEqual($result, "\n");
            return false;
        }
        return true;
    }

    public function uploadfile($url, $filename, $header = null)
    {
        set_time_limit(0); // sometimes this is slow when access the local webserver from external URL
        $fp = fopen($filename, 'r');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
        if (!is_null($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($ch, CURLOPT_HEADER, true);
        $page = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode != 200) {
            return false;
        }

        curl_close($ch);
        fclose($fp);
        unset($fp);
        return $page;
    }

    // Create or update a project and verify the changes made.
    public function createProject($input_settings, $update = false,
            $username = 'simpletest@localhost', $password = 'simpletest')
    {
        if ($update) {
            // Updating an existing project.
            if (!array_key_exists('Id', $input_settings)) {
                $this->fail("Project Id must be set");
                return false;
            }
            // Load its current settings.
            $project = new Project;
            $project->Id = $input_settings['Id'];
            $project->Fill();
            $settings = get_object_vars($project);
            $submit_button = 'Update';
        } else {
            // Create a new project.
            if (!array_key_exists('Name', $input_settings)) {
                $this->fail("Project name must be set");
                return false;
            }
            // Specify some default settings.
            $settings = array(
                    'AutoremoveMaxBuilds' => 500,
                    'AutoremoveTimeframe' => 60,
                    'CoverageThreshold' => 70,
                    'CvsViewerType' => 'viewcvs',
                    'EmailBrokenSubmission' => 1,
                    'EmailMaxChars' => 255,
                    'EmailMaxItems' => 5,
                    'NightlyTime' => '01:00:00 UTC',
                    'Public' => 1,
                    'ShowCoverageCode' => 1,
                    'TestTimeMaxStatus' => 3,
                    'TestTimeStd' => 4,
                    'TestTimeStdThreshold' => 1,
                    'UploadQuota' => 1);
            $submit_button = 'Submit';
        }

        // Override default/existing settings with those we wish to change.
        foreach ($input_settings as $k => $v) {
            $settings[$k] = $v;
        }

        // Login as admin.
        $client = $this->getGuzzleClient($username, $password);

        // Create project.
        try {
            $response = $client->request('POST',
                    $this->url . '/api/v1/project.php',
                    ['json' => [$submit_button => true, 'project' => $settings]]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }

        $response_array = json_decode($response->getBody(), true);
        $projectid = $response_array['project']['Id'];

        // Make sure all of our settings were applied successfully.
        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();
        if (!$project->Exists()) {
            $this->fail("Project does not exist after it should have been created");
        }
        foreach ($input_settings as $k => $v) {
            if ($k === 'repositories') {
                // Special handling for repositories as these aren't
                // simple project properties.
                $added_repos = $v;
                $num_added_repos = count($added_repos);
                $project_repos = $project->GetRepositories();
                $matches_found = 0;
                foreach ($project_repos as $project_repo) {
                    foreach ($added_repos as $added_repo) {
                        if ($project_repo['url'] === $added_repo['url'] &&
                                $project_repo['branch'] === $added_repo['branch'] &&
                                $project_repo['username'] === $added_repo['username'] &&
                                $project_repo['password'] === $added_repo['password']) {
                            $matches_found++;
                        }
                    }
                }
                if ($matches_found != count($added_repos)) {
                    $this->fail("Attempted to add $num_added_repos but only found $matches_found");
                }
            } else {
                $found_value = $project->{$k};
                if ($found_value != $v) {
                    $this->fail("Expected $v but found $found_value for $k");
                }
            }
        }
        return $projectid;
    }

    // Delete project.
    public function deleteProject($projectid)
    {
        // Login as admin.
        $client = $this->getGuzzleClient();

        // Delete project.
        $project_array = array('Id' => $projectid);
        try {
            $response = $client->delete(
                    $this->url . '/api/v1/project.php',
                    ['json' => ['project' => $project_array]]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }

        // Make sure the project doesn't exist anymore.
        $project = new Project();
        $project->Id = $projectid;
        if ($project->Exists()) {
            $this->fail("Project $projectid still exists after it should have been deleted");
        }
    }

    public function getGuzzleClient($username = 'simpletest@localhost',
                                    $password = 'simpletest')
    {
        $client = new GuzzleHttp\Client(['cookies' => true]);
        try {
            $response = $client->request('POST',
                    $this->url . '/user.php',
                    ['form_params' => [
                    'login' => $username,
                    'passwd' => $password,
                    'sent' => 'Login >>']]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }
        return $client;
    }

    public function addLineToConfig($line_to_add)
    {
        $contents = file_get_contents($this->configfilename);
        $handle = fopen($this->configfilename, 'w');

        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            if (strpos($line, '?>') !== false) {
                fwrite($handle, "$line_to_add\n");
            }
            if ($line != '') {
                fwrite($handle, "$line\n");
            }
        }
        fclose($handle);
        unset($handle);
        $this->pass('Passed');
    }

    public function removeLineFromConfig($line_to_remove)
    {
        if (empty($line_to_remove)) {
            return;
        }

        $contents = file_get_contents($this->configfilename);
        $handle = fopen($this->configfilename, 'w');
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            if (strpos($line_to_remove, $line) !== false) {
                continue;
            } elseif ($line != '') {
                fwrite($handle, "$line\n");
            }
        }
        fclose($handle);
    }
}

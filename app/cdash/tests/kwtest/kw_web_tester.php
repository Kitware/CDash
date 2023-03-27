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

require_once dirname(__FILE__) . '/../../../../vendor/autoload.php';
require_once dirname(__FILE__) . '/kw_unlink.php';

use App\Http\Controllers\CDash;
use App\Models\User;
use CDash\Config;
use CDash\Model\Project;
use App\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\CreatesApplication;

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
    use CreatesApplication;

    public $url = null;
    public $db = null;
    public $logfilename = null;
    public $configfilename = null;

    private $config;
    protected $app;
    protected $actingAs = [];
    protected $ctest_submission = null;

    /**
     * KWWebTestCase constructor.
     */
    public function __construct()
    {
        parent::__construct();

        global $configure;
        $this->url = $configure['urlwebsite'];

        $config = Config::getInstance();
        $this->configfilename = "{$config->get('CDASH_ROOT_DIR')}/../../.env";
        $this->config = $config;

        // Create the application on construct so that we have access to app() (container)
        $this->app = $this->createApplication();
        $this->logfilename = Log::getLogger()->getHandlers()[0]->getUrl();

        $db_type = config('database.default');
        $db_config = config("database.connections.{$db_type}");
        $this->db = new database($db_type);
        $this->db->setDb($db_config['database']);
        $this->db->setHost($db_config['host']);
        $this->db->setUser($db_config['username']);
        $this->db->setPassword($db_config['password']);
    }

    public function createBrowser()
    {
        $this->actingAs = [];
        return new CDashControllerBrowser($this);
    }

    public function config($var_name)
    {
        return $this->config->get($var_name);
    }

    public function setUp()
    {
        $this->removeParsedFiles();
        $this->startCodeCoverage();
    }

    public function removeParsedFiles()
    {
        $files = Storage::allFiles('parsed');
        Storage::delete($files);
    }

    public function tearDown()
    {
        $this->stopCodeCoverage();
        unset($_SERVER['Authorization']);
        foreach (array_keys($_SERVER) as $key) {
            if (strpos($key, 'HTTP_') === 0) {
                unset($_SERVER[$key]);
            }
        }

        $_POST = [];
        $_REQUEST = [];
        $_FILES = [];
        $_GET = [];
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
            $config = Config::getInstance();
            $data = xdebug_get_code_coverage();
            xdebug_stop_code_coverage();
            //echo "xdebug_stop_code_coverage called...\n";
            $file = config('cdash.coverage_dir') . DIRECTORY_SEPARATOR .
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
            // Delete file:
            cdash_testsuite_unlink($filename);
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

    /** a slightly more sane method of testing the log */
    protected function assertLogContains($expected, $lineCount)
    {
        $count = 0;
        $log = file_get_contents($this->logfilename);
        $lines = explode(PHP_EOL, $log);
        $passed = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if (!isset($expected[$count]) || ($line && !Str::contains($line, $expected[$count]))) {
                $message = "Unexpected output in logfile:\n"
                    . "Expected: {$expected[$count]}\n"
                    . "   Found: {$line}\n";
                $this->fail($message);
                $passed = false;
                break;
            }
            $count += $line ? 1 : 0;
            if (count($expected) >= $count) {
                break;
            }
        }

        $count = count($lines);
        if ($count !== $lineCount) {
            $message = "\nExpected {$lineCount} lines of log output, received {$count}";
            $this->fail($message);
            $passed = false;
        }

        return $passed;
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

    public function getApp()
    {
        return $this->app;
    }

    public function actingAs(array $credentials)
    {
        $this->actingAs = $credentials;
        return $this;
    }

    public function hasActingAs()
    {
        return !empty($this->actingAs);
    }

    public function login($user = 'simpletest@localhost', $passwd = 'simpletest')
    {
        $this->actingAs(['email' => $user, 'password' => $passwd]);
    }

    public function loginActingAs()
    {
        $user = $this->getUser($this->actingAs['email']);
        \Auth::shouldReceive('check')->andReturn(true);
        \Auth::shouldReceive('user')->andReturn($user);
        \Auth::shouldReceive('id')->andReturn($user->id);
    }

    public function logout()
    {
        \Auth::shouldReceive('check')->andReturn(false);
        \Auth::shouldReceive('user')->andReturn(new User);
        \Auth::shouldReceive('id')->andReturn(null);
    }

    public function getCtestSubmission()
    {
        return $this->ctest_submission;
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

    public function getUser($email)
    {
        return User::where('email', $email)->first();
    }

    public function createUser(array $fields = [])
    {
        if (isset($fields['password'])) {
            $fields['password'] = password_hash($fields['password'], PASSWORD_DEFAULT);
        }

        $user = factory(\App\Models\User::class)->create($fields);
        return $user;
    }

    private function check_submission_result($result)
    {
        if ($result === false) {
            return false;
        }

        if (Config::getInstance()->get('CDASH_BERNARD_SUBMISSION')) {
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

    public function submission($projectname, $file, $header = null, $debug = false)
    {
        $url = $this->url . "/submit.php?project=$projectname";

        if ($debug) {
            $url .= "&XDEBUG_SESSION_START";
        }

        $result = $this->uploadfile($url, $file, $header);
        return $this->check_submission_result($result);
    }

    public function submission_assign_buildid($file, $project, $build, $site,
            $stamp, $subproject = null, $header = null)
    {
        $url = $this->url . "/submit.php?project=$project&build=$build&site=$site&stamp=$stamp";
        if (!is_null($subproject)) {
            $url .= "&subproject=$subproject";
        }

        $result = $this->uploadfile($url, $file, $header);
        $pattern = '#<buildId>([0-9]+)</buildId>#';
        if ($this->check_submission_result($result) &&
            preg_match($pattern, $result, $matches) &&
            isset($matches[1])) {
            return $matches[1];
        }

        return false;
    }

    public function setRequestHeaders($headers)
    {
        /** @var CDashControllerBrowser $browser */
        $browser = $this->getBrowser();
        foreach ($headers as $header => $value) {
            $browser->addRequestHeader($header, $value);
        }
    }

    public function putCtestFile(
        $filename,
        array $query,
        $parameters = [],
        $contentType = 'text/xml'
    ) {
        $this->ctest_submission = $filename;
        $qstr = '';
        array_walk($query, function ($v, $k) use (&$qstr) {
            $qstr .= "{$k}={$v}&";
        });

        $url = "{$this->url}/submit.php?{$qstr}";
        return $this->put($url, $parameters, $contentType);
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
                    'UploadQuota' => 1,
                    'ViewSubProjectsLink' => 1,
                    'WarningsFilter' => '',
                    'ErrorsFilter' => '');
            $submit_button = 'Submit';
        }

        // Override default/existing settings with those we wish to change.
        foreach ($input_settings as $k => $v) {
            $settings[$k] = $v;
        }

        // Login as admin.
        $client = $this->getGuzzleClient($username, $password);

        if (!$client) {
            return false;
        }

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
            // first get the csrf token
            $response = $client->request('GET', "{$this->url}/login");
            $html = "{$response->getBody()}";
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            $token = $dom->getElementById('csrf-token')
                ->getAttribute('value');

            $response = $client->request('POST',
                $this->url . '/login',
                ['form_params' => [
                    '_token' => "{$token}",
                'email' => $username,
                'password' => $password,
                'sent' => 'Login >>']]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }
        return $client;
    }

    /**
     * @param $line_to_add
     * @deprecated DO NOT USE
     */
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

    /**
     * @param $line_to_remove
     * @drepecated DO NOT USE
     */
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

    public function expectsPageRequiresLogin($page)
    {
        $this->logout();
        $content = $this->get("{$this->url}{$page}");

        if (strpos($content, '<form method="post" action="login" name="loginform" id="loginform">') === false) {
            $this->fail("Login not found when expected");
            return false;
        }
        return true;
    }
}

/**
 * Class CDashControllerBrowser
 */
class CDashControllerBrowser extends SimpleBrowser
{
    /** @var KWWebTestCase $test */
    private $test;

    /** @var array $headers */
    private $headers;

    private $files;

    public function __construct($test)
    {
        $this->test = $test;
        $this->headers = [];
        $this->files = [];
        parent::__construct();
    }

    public function __destruct()
    {
        if (!empty($this->files)) {
            foreach ($this->files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function addRequestHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * @return CDashControllerUserAgent
     */
    public function createUserAgent()
    {
        return new CDashControllerUserAgent($this->test);
    }

    /**
     * @param SimpleUrl $url
     * @param SimpleEncoding $encoding
     * @param int $depth
     * @return SimplePage
     */
    protected function fetch($url, $encoding, $depth = 0)
    {
        $this->setRequestParameters($encoding);
        $this->setQueryParameters($url);
        $this->setServerParameters();
        $this->setFileParameters($encoding);

        $_REQUEST = array_merge($_REQUEST, $_GET, $_POST);

        return parent::fetch($url, $encoding, $depth);
    }

    private function setFileParameters($encoding)
    {
        if (is_a($encoding, SimpleMultipartEncoding::class)) {
            /** @var SimpleAttachment $part */
            foreach ($encoding->getAll() as $part) {
                if (is_a($part, SimpleAttachment::class)) {
                    // one hates to have to do this unless absolutely necessary
                    $reflection = new \ReflectionClass(SimpleAttachment::class);
                    $property = $reflection->getProperty('content');
                    $property->setAccessible(true);
                    $content = $property->getValue($part);
                    if ($tmpname = tempnam(sys_get_temp_dir(), 'simpletest')) {
                        $fh = fopen($tmpname, 'w');
                        fwrite($fh, $content);
                        fclose($fh);
                        $_FILES[$part->getKey()] = [
                            'name' => $part->getValue(),
                            'tmp_name' => $tmpname,
                            'size' => filesize($tmpname),
                            'type' => mime_content_type($tmpname),
                        ];
                        // for deletion upon destructor call
                        $this->files[] = $tmpname;
                    }
                }
            }
        }
    }

    private function setServerParameters()
    {
        $_SERVER = array_merge($_SERVER, $this->headers);
    }

    /**
     * @param SimpleEncoding $encoding
     * @return void
     */
    private function setRequestParameters($encoding)
    {
        // reset our magic request vars
        $parameters = $_REQUEST = $_GET = $_POST = [];

        /** @var SimpleEncodedPair $parameter */
        foreach ($encoding->getAll() as $parameter) {
            $key = $parameter->getKey();
            $value = $parameter->getValue();
            $this->setRequestKeyValuePair($parameters, $key, $value);
        }

        if ($encoding instanceof SimpleGetEncoding) {
            $_GET = $parameters;
        } else {
            $_POST = $parameters;
        }
    }

    /**
     * @param SimpleUrl $url
     */
    private function setQueryParameters($url)
    {
        $query = $url->getEncodedRequest();
        $query = str_replace('?', '', $query);
        $parameters = [];

        if (!empty($query)) {
            foreach (explode("&", $query) as $parameter) {
                if (strpos($parameter, '=') !== false) {
                    list($key, $value) = explode('=', $parameter);
                    $this->setRequestKeyValuePair($parameters, $key, $value);
                } else {
                    $this->setRequestKeyValuePair($parameters, $parameter, '');
                }
            }
        }

        $_GET = array_merge($_GET, $parameters);
    }

    /**
     * @param array &$parameters
     * @param string $key
     * @param mixed $value
     */
    private function setRequestKeyValuePair(&$parameters, $key, $value)
    {
        $value = urldecode($value);
        $key = urldecode($key);

        // Handle key names that represent arrays of values
        if (preg_match('/^(\w+)\[(\w+)\]=?$/', $key, $parts)) {
            list(, $key, $index) = $parts;
            if (!isset($parameters[$key])) {
                $parameters[$key] = [];
            }
            $parameters[$key][$index] = $value;
        } elseif (preg_match('/^(\w+)\[]$/', $key, $parts)) {
            list(, $key) = $parts;
            if (!isset($parameters[$key])) {
                $parameters[$key] = [];
            }
            $parameters[$key][] = $value;
        } else {
            $parameters[$key] = $value;
        }
    }
}


class CDashControllerUserAgent extends SimpleUserAgent
{
    use CreatesApplication;

    /** @var KWWebTestCase $test */
    private $test;

    /** @var Response $response */
    private $response;

    public function __construct(KWWebTestCase $test)
    {
        parent::__construct();
        $this->test = $test;
    }
    /**
     * @param SimpleUrl $url
     * @param SimpleEncoding $encoding
     * @return SimpleHttpResponse
     */
    protected function fetch($url, $encoding)
    {
        $url_string = $url->asString();
        // Strip $CDASH_DIR_NAME (if set) from the URL string.
        global $configure;
        if (strlen($configure['webpath']) > 1) {
            $url_string = str_replace($configure['webpath'], '', $url_string);
        }

        $request = $this->getIlluminateHttpRequest($url_string, $encoding);
        $config_cache = config('cdash');

        // The application *MUST* be recreated for every request
        $app = $this->createApplication();

        // Config settings are loaded from file upon app bootstrap which occurs in
        // createApplication(). Because we recreate the application with each request
        // we must ensure that our the config settings manipulated in individual tests
        // persist.
        config(['cdash' => $config_cache]);

        $kernel = $app->make(Kernel::class);
        if ($this->test->hasActingAs()) {
            $this->test->loginActingAs();
        }
        $this->response = $kernel->handle($request);
        return $this->getSimpleHttpResponse($url, $encoding);
    }

    /**
     * @param String $url_string
     * @param SimpleEncoding|SimpleGetEncoding|SimplePutEncoding|SimplePostEncoding $encoding
     * @return Request
     */
    private function getIlluminateHttpRequest($url_string, $encoding)
    {
        $contents = null;
        if ($submission = $this->test->getCtestSubmission()) {
            $contents = file_get_contents($submission);
        }

        $request = Request::create(
            $url_string,
            $encoding->getMethod(),
            $_REQUEST,
            $_COOKIE,
            [],
            $_SERVER,
            $contents
        );

        // Symphony\Component\HttpFoundation\Request sets some default
        // $_SERVER values for us but it only makes them accessible through
        // the Request object, i.e. it will not set them in $_SERVER; here
        // we will make sure that they get set in the $_SERVER superglobal
        $parameters = [];
        foreach ($request->server() as $key => $value) {
            $parameters[$key] = $value;
        }

        $_SERVER = array_merge($_SERVER, $parameters);

        return $request;
    }

    /**
     * @param $url
     * @param $encoding
     * @return SimpleHttpResponse
     */
    private function getSimpleHttpResponse($url, $encoding)
    {
        $socket = $this->getSocketEmulator();
        return new SimpleHttpResponse($socket, $url, $encoding);
    }

    /**
     * @return object
     */
    private function getSocketEmulator()
    {
        $socket = new class($this->response) {
            private $read = false;
            /** @var Response $body */
            private $response;

            public function __construct($response)
            {
                $this->response = $response;
            }

            public function read()
            {
                $output = '';
                if (!$this->read) {
                    if (is_a($this->response, StreamedResponse::class)) {
                        ob_start();
                        $this->response->send();
                        $file = ob_get_contents();
                        ob_end_clean();
                        $output = "{$this->response}{$file}";
                    } else {
                        $output = "{$this->response->sendHeaders()}";
                    }

                    $this->read = true;
                }
                return $output;
            }

            public function getSent()
            {
                return true;
            }

            public function isError()
            {
                $this->response->isServerError() ?:
                    $this->response->isClientError() ?:
                        false;
            }
        };
        return $socket;
    }
}

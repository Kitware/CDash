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

// This is used by several of the tests, but the Laravel entrypoint is not used for
// such tests, meaning that this could be undefined.
if (!defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

use App\Http\Kernel;
use App\Models\User;
use CDash\Model\Project;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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

    public $url;
    public $db;
    public $logfilename;

    protected $app;
    protected $actingAs = [];
    protected $ctest_submission;

    /**
     * KWWebTestCase constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // Create the application on construct so that we have access to app() (container)
        $this->app = $this->createApplication();
        $this->logfilename = Log::getLogger()->getHandlers()[0]->getUrl();

        $this->url = config('app.url');

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
        // echo "startCodeCoverage called...\n";
        if (extension_loaded('xdebug')) {
            xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
            // echo "xdebug_start_code_coverage called...\n";
        }
    }

    public function stopCodeCoverage()
    {
        // echo "stopCodeCoverage called...\n";
        if (extension_loaded('xdebug')) {
            $data = xdebug_get_code_coverage();
            xdebug_stop_code_coverage();
            // echo "xdebug_stop_code_coverage called...\n";
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
     *
     * @param string $mystring
     * @param string $findme
     *
     * @return true if the search string has found or false in the other case
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
     *
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
            if ($this->findString($content, 'ERROR')
                || $this->findString($content, 'WARNING')
            ) {
                $this->fail('Log file has errors or warnings... ' . var_export($content, true));
                return false;
            }
            return $content;
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
            throw new Exception($message);
            $this->fail($message);
            $passed = false;
        }

        return $passed;
    }

    /**
     * Analyse a website page
     *
     * @param object $page
     *
     * @return string|false the content of the page if there is no errors
     *                      otherwise false
     */
    public function analyse($page): string|false
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
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        Auth::shouldReceive('userResolver')->andReturn(function () use ($user) {
            return $user;
        });
        Auth::shouldReceive('id')->andReturn($user->id);
        Auth::shouldReceive('guard')->andReturnSelf();
        Auth::shouldReceive('shouldUse')->andReturn('web');
    }

    public function logout()
    {
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('user')->andReturn(new User());
        Auth::shouldReceive('id')->andReturn(null);
    }

    public function getCtestSubmission()
    {
        return $this->ctest_submission;
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

        $user = User::create($fields);
        return $user;
    }

    private function check_submission_result($result)
    {
        if ($result === false) {
            return false;
        }

        if ($this->findString($result, 'error')
            || $this->findString($result, 'Warning')
            || $this->findString($result, 'Notice')
        ) {
            $this->assertEqual($result, "\n");
            return false;
        }
        return true;
    }

    /**
     * @deprecated 12/09/2024  Use \Test\Traits\CreatesSubmissions instead
     */
    public function submission($projectname, $file, $header = null, $debug = false)
    {
        $url = $this->url . "/submit.php?project=$projectname";

        if ($debug) {
            $url .= '&XDEBUG_SESSION_START';
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
        if ($this->check_submission_result($result)
            && preg_match($pattern, $result, $matches)
            && isset($matches[1])) {
            return $matches[1];
        }

        return false;
    }

    public function putCtestFile(
        $filename,
        array $query,
        $parameters = [],
        $contentType = 'text/xml',
    ) {
        $this->ctest_submission = $filename;
        $qstr = '';
        array_walk($query, function ($v, $k) use (&$qstr) {
            $qstr .= "{$k}={$v}&";
        });

        $url = "{$this->url}/submit.php?{$qstr}";
        return $this->put($url, $parameters, $contentType);
    }

    /**
     * @deprecated 12/09/2024  Use \Test\Traits\CreatesSubmissions instead
     */
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
                $this->fail('Project Id must be set');
                return false;
            }
            // Load its current settings.
            $project = new Project();
            $project->Id = $input_settings['Id'];
            $project->Fill();
            $settings = get_object_vars($project);
            $submit_button = 'Update';
        } else {
            // Create a new project.
            if (!array_key_exists('Name', $input_settings)) {
                $this->fail('Project name must be set');
                return false;
            }
            // Specify some default settings.
            $settings = [
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
                'ErrorsFilter' => ''];
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
        } catch (ClientException $e) {
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
            $this->fail('Project does not exist after it should have been created');
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
                        if ($project_repo['url'] === $added_repo['url']
                                && $project_repo['branch'] === $added_repo['branch']
                                && $project_repo['username'] === $added_repo['username']
                                && $project_repo['password'] === $added_repo['password']) {
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
        $project_array = ['Id' => $projectid];
        try {
            $response = $client->delete(
                $this->url . '/api/v1/project.php',
                ['json' => ['project' => $project_array]]);
        } catch (ClientException $e) {
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
        $client = new Client(['cookies' => true]);
        try {
            // first get the csrf token
            $response = $client->request('GET', "{$this->url}/login");
            $html = "{$response->getBody()}";
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $token = $dom->getElementById('csrf-token')
                ->getAttribute('value');

            $response = $client->request('POST',
                $this->url . '/login',
                ['form_params' => [
                    '_token' => "{$token}",
                    'email' => $username,
                    'password' => $password,
                    'sent' => 'Login >>']]);
        } catch (ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }
        return $client;
    }

    public function expectsPageRequiresLogin($page)
    {
        $this->logout();
        $content = $this->get("{$this->url}{$page}");

        if (strpos($content, '<form method="POST" action="login" name="loginform" id="loginform">') === false) {
            $this->fail('Login not found when expected');
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
    /** @var KWWebTestCase */
    private $test;

    /** @var array */
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
     *
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
                    $reflection = new ReflectionClass(SimpleAttachment::class);
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
     *
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
            foreach (explode('&', $query) as $parameter) {
                if (strpos($parameter, '=') !== false) {
                    [$key, $value] = explode('=', $parameter);
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
     */
    private function setRequestKeyValuePair(&$parameters, $key, $value)
    {
        $value = urldecode($value);
        $key = urldecode($key);

        // Handle key names that represent arrays of values
        if (preg_match('/^(\w+)\[(\w+)\]=?$/', $key, $parts)) {
            [, $key, $index] = $parts;
            if (!isset($parameters[$key])) {
                $parameters[$key] = [];
            }
            $parameters[$key][$index] = $value;
        } elseif (preg_match('/^(\w+)\[]$/', $key, $parts)) {
            [, $key] = $parts;
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

    /** @var KWWebTestCase */
    private $test;

    /** @var Response */
    private $response;

    public function __construct(KWWebTestCase $test)
    {
        parent::__construct();
        $this->test = $test;
    }

    /**
     * @param SimpleUrl $url
     * @param SimpleEncoding $encoding
     *
     * @return SimpleHttpResponse
     */
    protected function fetch($url, $encoding)
    {
        $config_cache = config('cdash');

        // The application *MUST* be recreated for every request
        $app = $this->createApplication();

        $url_string = str_replace(config('app.url'), '', $url->asString());
        $request = $this->getIlluminateHttpRequest($url_string, $encoding);

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
     * @param string $url_string
     * @param SimpleEncoding|SimpleGetEncoding|SimplePutEncoding|SimplePostEncoding $encoding
     *
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
            /** @var Response */
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

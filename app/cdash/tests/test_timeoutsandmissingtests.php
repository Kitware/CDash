<?php

use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/cdash_test_case.php';

class TimeoutsAndMissingTestsTestCase extends KWWebTestCase
{
    private $buildName;

    public function __construct()
    {
        parent::__construct();
        $this->buildName = 'Win32-MSVC2009';
    }

    private function getLastBuildId()
    {
        $sql = "
          SELECT id
          FROM build
          WHERE name='{$this->buildName}'
          ORDER BY starttime DESC
          LIMIT 1
         ";

        $query = DB::select($sql)[0];
        return $query->id;
    }

    public function testMissingTestsSummarizedInEmail(): void
    {
        $this->deleteLog($this->logfilename);
        $rep = __DIR__ . '/data/TimeoutsAndMissingTests';
        $file = "{$rep}/5_test.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            $this->fail('submission of test data failed');
            return;
        }

        if (!$this->checkLog($this->logfilename)) {
            $this->fail('Errors in log after submit');
        }

        $url = url('/');
        $expected = [
            'simpletest@localhost',
            'FAILED (m=3): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has missing tests.',
            "Details on the submission can be found at {$url}/builds/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-26 10:04:00',
            'Type: Nightly',
            'Total Missing Tests: 3',
            '*Missing Tests*',
            "DashboardSendTest ({$url}/builds/",
            "Parser1Test1 ({$url}/builds/",
            "SystemInfoTest ({$url}/builds/",
            '-CDash on',
        ];
        if ($this->assertLogContains($expected, 18)) {
            $this->pass('Passed');
        }
    }

    public function testMissingTestsSummarizedInViewTestAPI(): void
    {
        $id = $this->getLastBuildId();

        $url = "{$this->url}/api/v1/viewTest.php?buildid={$id}";
        $this->get($url);
        $json = $this->getBrowser()->getContent();

        $response = json_decode($json, true);
        $tests = [];

        foreach ($response['tests'] as $test) {
            $tests[$test['name']] = $test;
        }

        $this->assertEqual($response['numMissing'], 3);

        $this->assertEqual($tests['SystemInfoTest']['status'], 'Missing');
        $this->assertEqual($tests['DashboardSendTest']['status'], 'Missing');
        $this->assertEqual($tests['Parser1Test1']['status'], 'Missing');

        $this->assertNotEqual($tests['curl']['status'], 'Missing');
        $this->assertNotEqual($tests['FileActionsTest']['status'], 'Missing');
        $this->assertNotEqual($tests['StringActionsTest']['status'], 'Missing');
        $this->assertNotEqual($tests['MathActionsTest']['status'], 'Missing');
    }

    public function testTimeoutFailuresDifferentiatedInEmail(): void
    {
        $this->deleteLog($this->logfilename);
        $rep = __DIR__ . '/data/TimeoutsAndMissingTests';
        $file = "{$rep}/4_test.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            $this->fail('failed to submit test data');
            return;
        }
        $url = url('/');
        $expected = [
            'simpletest@localhost',
            'FAILED (t=2): EmailProjectExample - OSX-SIERRA-10.12.1 - Nightly',
            'A submission to CDash for the project EmailProjectExample has failing tests',
            "Details on the submission can be found at {$url}/builds/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: OSX-SIERRA-10.12.1',
            'Build Time: 2009-02-23 10:04:13',
            'Type: Nightly',
            'Total Failing Tests: 2',
            '*Failing Tests*',
            "SleepTimer1 | Completed (Timeout) | ({$url}/tests/",
            "SleepTimer2 | Completed (Timeout) | ({$url}/tests/",
            '-CDash on',
        ];
        if ($this->assertLogContains($expected, 17)) {
            $this->pass('Passed');
        }
    }
}

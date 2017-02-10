<?php
/**
 * Created by PhpStorm.
 * User: bryonbean
 * Date: 2/6/17
 * Time: 12:32 PM
 */

require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class TimeoutsAndMissingTestsTestCase extends KWWebTestCase
{
    private $buildName;

    public function __construct()
    {
        parent::__construct();
        $this->buildName = 'Win32-MSVC2009';
    }

    private function getLastBuildId ()
    {
        $sql = "
          SELECT `id` 
          FROM `build` 
          WHERE `name`='{$this->buildName}'
          ORDER BY `starttime` DESC
          LIMIT 1
         ";

        $query = pdo_single_row_query($sql);
        return $query['id'];
    }

    public function testMissingTestsSummarizedInEmail()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/TimeoutsAndMissingTests';
        $file = "{$rep}/5_test.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        if (!$this->compareLog($this->logfilename, "{$rep}/cdash_5.log")) {
            return;
        }

        $this->pass('Passed');
    }

    public function testMissingTestsSummarizedInViewTestAPI()
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

    public function testTimeoutFailuresDifferentiatedInEmail()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/TimeoutsAndMissingTests';
        $file = "{$rep}/4_test.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        if (!$this->compareLog($this->logfilename, "$rep/cdash_4.log")) {
            return;
        }
        $this->pass('Passed');
    }
}